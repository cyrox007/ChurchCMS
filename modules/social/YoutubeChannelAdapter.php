<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class YoutubeChannelAdapter implements
    ChannelAdapter,
    ChannelConnectionTester
{
    private const API_BASE = 'https://www.googleapis.com/youtube/v3';

    public function __construct(
        private readonly ChannelHttpClient $http =
            new NativeHttpClient(),
    ) {
    }

    public function providerId(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return 'YouTube';
    }

    public function capabilities(): array
    {
        return [
            ChannelCapability::IMPORT_VIDEO,
            ChannelCapability::POLLING,
        ];
    }

    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult {
        $channelId = trim($targetRef);
        $apiKey = trim($credentials);

        if (
            !self::validChannelId($channelId)
            || !self::validApiKey($apiKey)
        ) {
            return new ChannelConnectionTestResult(
                false,
                'Проверьте YouTube channel ID и API key.',
            );
        }

        try {
            $channel = $this->channel(
                $channelId,
                $apiKey,
            );
        } catch (Throwable) {
            return new ChannelConnectionTestResult(
                false,
                'YouTube Data API не подтвердил канал или API key.',
            );
        }

        $uploads = self::uploadsPlaylistId($channel);

        if ($uploads === null) {
            return new ChannelConnectionTestResult(
                false,
                'YouTube не вернул системный uploads-плейлист канала.',
            );
        }

        return new ChannelConnectionTestResult(
            true,
            'Публичный канал YouTube доступен для входящей синхронизации.',
        );
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        return new ChannelPublishResult(
            false,
            error: 'Загрузка видео в YouTube пока требует Media upload и OAuth lifecycle.',
        );
    }

    public function pull(
        SocialConnection $connection,
        string $credentials,
        ?string $cursor,
        int $limit = 50,
    ): ChannelPullBatch {
        $channelId = trim($connection->targetRef);
        $apiKey = trim($credentials);

        if (
            !self::validChannelId($channelId)
            || !self::validApiKey($apiKey)
        ) {
            throw new RuntimeException(
                'Некорректные параметры YouTube подключения.'
            );
        }

        $channel = $this->channel(
            $channelId,
            $apiKey,
        );
        $playlistId = self::uploadsPlaylistId(
            $channel,
        );

        if ($playlistId === null) {
            throw new RuntimeException(
                'YouTube не вернул uploads-плейлист канала.'
            );
        }

        $limit = max(1, min(50, $limit));
        $result = $this->api(
            '/playlistItems',
            [
                'part' => 'snippet,contentDetails,status',
                'playlistId' => $playlistId,
                'maxResults' => 50,
                'key' => $apiKey,
            ],
        );

        $rows = is_array($result['items'] ?? null)
            ? $result['items']
            : [];

        $cursorPoint = self::parseCursor(
            $cursor,
        );
        $candidates = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = self::inboundItem(
                $row,
                $channelId,
            );
            $point = self::itemPoint($row);

            if (
                $item === null
                || $point === null
                || (
                    $cursorPoint !== null
                    && self::comparePoint(
                        $point,
                        $cursorPoint,
                    ) <= 0
                )
            ) {
                continue;
            }

            $candidates[] = [
                'point' => $point,
                'item' => $item,
            ];
        }

        usort(
            $candidates,
            static fn(array $left, array $right): int =>
                self::comparePoint(
                    $left['point'],
                    $right['point'],
                ),
        );

        $candidates = array_slice(
            $candidates,
            0,
            $limit,
        );

        $items = array_map(
            static fn(array $candidate): ChannelInboundItem =>
                $candidate['item'],
            $candidates,
        );

        $nextCursor = $cursor;
        if ($candidates !== []) {
            $last = $candidates[array_key_last($candidates)];
            $nextCursor = self::formatCursor(
                $last['point'],
            );
        }

        return new ChannelPullBatch(
            $items,
            $nextCursor,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function channel(
        string $channelId,
        string $apiKey,
    ): array {
        $result = $this->api(
            '/channels',
            [
                'part' => 'id,snippet,contentDetails',
                'id' => $channelId,
                'maxResults' => 1,
                'key' => $apiKey,
            ],
        );

        $items = $result['items'] ?? null;

        if (
            !is_array($items)
            || !is_array($items[0] ?? null)
            || (string) ($items[0]['id'] ?? '')
                !== $channelId
        ) {
            throw new RuntimeException(
                'YouTube channel не найден.'
            );
        }

        return $items[0];
    }

    /**
     * @param array<string,string|int|float|bool|null> $query
     * @return array<string,mixed>
     */
    private function api(
        string $path,
        array $query,
    ): array {
        $response = $this->http->getJson(
            self::API_BASE . $path,
            $query,
        );

        $status = (int) ($response['status'] ?? 0);
        $json = $response['json'] ?? null;

        if (
            $status < 200
            || $status >= 300
            || !is_array($json)
        ) {
            throw new RuntimeException(
                'YouTube Data API отклонил запрос.'
            );
        }

        if (is_array($json['error'] ?? null)) {
            $message = trim((string) (
                $json['error']['message'] ?? ''
            ));

            throw new RuntimeException(
                $message !== ''
                    ? 'YouTube Data API: '
                        . self::safeText($message, 240)
                    : 'YouTube Data API вернул ошибку.'
            );
        }

        return $json;
    }

    /**
     * @param array<string,mixed> $channel
     */
    private static function uploadsPlaylistId(
        array $channel,
    ): ?string {
        $content = $channel['contentDetails'] ?? null;
        $related = is_array($content)
            ? ($content['relatedPlaylists'] ?? null)
            : null;
        $uploads = is_array($related)
            ? trim((string) ($related['uploads'] ?? ''))
            : '';

        return $uploads !== '' ? $uploads : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function inboundItem(
        array $row,
        string $channelId,
    ): ?ChannelInboundItem {
        $snippet = $row['snippet'] ?? null;
        $content = $row['contentDetails'] ?? null;

        if (
            !is_array($snippet)
            || !is_array($content)
        ) {
            return null;
        }

        $resource = $snippet['resourceId'] ?? null;
        $videoId = trim((string) (
            $content['videoId']
            ?? (
                is_array($resource)
                    ? ($resource['videoId'] ?? '')
                    : ''
            )
        ));

        if ($videoId === '') {
            return null;
        }

        $ownerChannelId = trim((string) (
            $snippet['channelId'] ?? ''
        ));

        if (
            $ownerChannelId !== ''
            && $ownerChannelId !== $channelId
        ) {
            return null;
        }

        $publishedAt = self::publishedAt($row);
        if ($publishedAt === null) {
            return null;
        }

        return new ChannelInboundItem(
            remoteId: $videoId,
            kind: 'video',
            title: self::nullableText(
                $snippet['title'] ?? null,
            ),
            text: trim((string) (
                $snippet['description'] ?? ''
            )),
            canonicalUrl:
                'https://www.youtube.com/watch?v='
                . rawurlencode($videoId),
            media: [[
                'type' => 'video',
                'video_id' => $videoId,
                'thumbnail_url' =>
                    self::largestThumbnail(
                        $snippet['thumbnails'] ?? null,
                    ),
            ]],
            publishedAt: $publishedAt,
            updatedAt: null,
            payload: [
                'playlist_item_id' => (string) (
                    $row['id'] ?? ''
                ),
                'channel_id' => $channelId,
                'position' => (int) (
                    $snippet['position'] ?? 0
                ),
                'privacy_status' => is_array(
                    $row['status'] ?? null,
                )
                    ? (string) (
                        $row['status']['privacyStatus']
                        ?? ''
                    )
                    : '',
            ],
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array{timestamp:int,video_id:string}|null
     */
    private static function itemPoint(
        array $row,
    ): ?array {
        $content = $row['contentDetails'] ?? null;
        $snippet = $row['snippet'] ?? null;

        if (
            !is_array($content)
            || !is_array($snippet)
        ) {
            return null;
        }

        $resource = $snippet['resourceId'] ?? null;
        $videoId = trim((string) (
            $content['videoId']
            ?? (
                is_array($resource)
                    ? ($resource['videoId'] ?? '')
                    : ''
            )
        ));
        $publishedAt = self::publishedAt($row);

        if ($videoId === '' || $publishedAt === null) {
            return null;
        }

        return [
            'timestamp' => $publishedAt->getTimestamp(),
            'video_id' => $videoId,
        ];
    }

    /**
     * @return array{timestamp:int,video_id:string}|null
     */
    private static function parseCursor(
        ?string $cursor,
    ): ?array {
        if (
            $cursor === null
            || preg_match(
                '/^(\d+)\|([A-Za-z0-9_-]+)$/D',
                $cursor,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        return [
            'timestamp' => (int) $matches[1],
            'video_id' => $matches[2],
        ];
    }

    /**
     * @param array{timestamp:int,video_id:string} $point
     */
    private static function formatCursor(
        array $point,
    ): string {
        return $point['timestamp']
            . '|'
            . $point['video_id'];
    }

    /**
     * @param array{timestamp:int,video_id:string} $left
     * @param array{timestamp:int,video_id:string} $right
     */
    private static function comparePoint(
        array $left,
        array $right,
    ): int {
        return [
            $left['timestamp'],
            $left['video_id'],
        ] <=> [
            $right['timestamp'],
            $right['video_id'],
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function publishedAt(
        array $row,
    ): ?DateTimeImmutable {
        $content = $row['contentDetails'] ?? null;
        $snippet = $row['snippet'] ?? null;

        $value = is_array($content)
            ? trim((string) (
                $content['videoPublishedAt'] ?? ''
            ))
            : '';

        if ($value === '' && is_array($snippet)) {
            $value = trim((string) (
                $snippet['publishedAt'] ?? ''
            ));
        }

        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private static function largestThumbnail(
        mixed $thumbnails,
    ): ?string {
        if (!is_array($thumbnails)) {
            return null;
        }

        $bestUrl = null;
        $bestArea = -1;

        foreach ($thumbnails as $thumbnail) {
            if (!is_array($thumbnail)) {
                continue;
            }

            $url = trim((string) (
                $thumbnail['url'] ?? ''
            ));
            if ($url === '') {
                continue;
            }

            $area = (int) (
                $thumbnail['width'] ?? 0
            ) * (int) (
                $thumbnail['height'] ?? 0
            );

            if ($area >= $bestArea) {
                $bestArea = $area;
                $bestUrl = $url;
            }
        }

        return $bestUrl;
    }

    private static function nullableText(
        mixed $value,
    ): ?string {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function validChannelId(
        string $value,
    ): bool {
        return preg_match(
            '/^UC[A-Za-z0-9_-]{22}$/D',
            trim($value),
        ) === 1;
    }

    private static function validApiKey(
        string $value,
    ): bool {
        $value = trim($value);

        return $value !== ''
            && strlen($value) <= 512
            && preg_match('/\s/u', $value) !== 1;
    }

    private static function safeText(
        string $value,
        int $limit,
    ): string {
        $value = str_replace(
            ["\r", "\n", "\0"],
            [' ', ' ', ''],
            trim($value),
        );

        return substr($value, 0, $limit);
    }
}
