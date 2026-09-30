<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class RutubeChannelAdapter implements
    ChannelAdapter,
    ChannelConnectionTester,
    ChannelCredentialsOptional
{
    private const API_BASE =
        'https://rutube.ru/api/video/person/';
    private const MAX_PAGES = 50;

    public function __construct(
        private readonly ChannelHttpClient $http =
            new NativeHttpClient(),
    ) {
    }

    public function providerId(): string
    {
        return 'rutube';
    }

    public function label(): string
    {
        return 'Rutube';
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
        $personId = self::personId($targetRef);

        if ($personId === null || trim($credentials) !== '') {
            return new ChannelConnectionTestResult(
                false,
                'Укажите числовой ID канала Rutube и оставьте поле секрета пустым.',
            );
        }

        try {
            $page = $this->page(
                $personId,
                1,
            );
        } catch (Throwable) {
            return new ChannelConnectionTestResult(
                false,
                'Rutube не подтвердил публичный канал.',
            );
        }

        if (!is_array($page['results'] ?? null)) {
            return new ChannelConnectionTestResult(
                false,
                'Rutube вернул некорректный список видео канала.',
            );
        }

        return new ChannelConnectionTestResult(
            true,
            'Публичный канал Rutube доступен для входящей синхронизации.',
        );
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        return new ChannelPublishResult(
            false,
            error: 'Загрузка видео в Rutube пока требует отдельного подтверждённого upload API и готового Media pipeline.',
        );
    }

    public function pull(
        SocialConnection $connection,
        string $credentials,
        ?string $cursor,
        int $limit = 50,
    ): ChannelPullBatch {
        $personId = self::personId(
            $connection->targetRef,
        );

        if ($personId === null) {
            throw new RuntimeException(
                'Некорректный ID канала Rutube.'
            );
        }

        $limit = max(1, min(50, $limit));
        $cursorPoint = self::parseCursor($cursor);
        $rows = $this->rowsUntilCursor(
            $personId,
            $cursorPoint,
        );
        $candidates = [];

        foreach ($rows as $row) {
            $point = self::rowPoint($row);
            $item = self::inboundItem($row);

            if (
                $point === null
                || $item === null
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
     * @param array{timestamp:int,video_id:string}|null $cursorPoint
     * @return list<array<string,mixed>>
     */
    private function rowsUntilCursor(
        int $personId,
        ?array $cursorPoint,
    ): array {
        $rows = [];
        $pageNumber = 1;
        $reachedCursor = $cursorPoint === null;

        while (true) {
            $page = $this->page(
                $personId,
                $pageNumber,
            );
            $results = is_array($page['results'] ?? null)
                ? $page['results']
                : [];

            foreach ($results as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $rows[] = $row;

                if ($cursorPoint === null) {
                    continue;
                }

                $point = self::rowPoint($row);
                if (
                    $point !== null
                    && self::comparePoint(
                        $point,
                        $cursorPoint,
                    ) <= 0
                ) {
                    $reachedCursor = true;
                }
            }

            if ($cursorPoint === null) {
                break;
            }

            if (
                $reachedCursor
                || !self::boolValue($page['has_next'] ?? false)
            ) {
                break;
            }

            if ($pageNumber >= self::MAX_PAGES) {
                throw new RuntimeException(
                    'Rutube накопил слишком много изменений между синхронизациями; '
                    . 'cursor не найден в первых 50 страницах.'
                );
            }

            $pageNumber++;
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private function page(
        int $personId,
        int $pageNumber,
    ): array {
        $response = $this->http->getJson(
            self::API_BASE
                . rawurlencode((string) $personId)
                . '/',
            [
                'page' => max(1, $pageNumber),
                'format' => 'json',
            ],
        );

        $status = (int) ($response['status'] ?? 0);
        $json = $response['json'] ?? null;

        if (
            $status < 200
            || $status >= 300
            || !is_array($json)
            || !is_array($json['results'] ?? null)
        ) {
            throw new RuntimeException(
                'Публичный API Rutube отклонил запрос.'
            );
        }

        return $json;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function inboundItem(
        array $row,
    ): ?ChannelInboundItem {
        $videoId = self::videoId($row['id'] ?? null);
        $publishedAt = self::publishedAt($row);

        if ($videoId === null || $publishedAt === null) {
            return null;
        }

        $thumbnail = self::httpsUrl(
            $row['thumbnail_url'] ?? null,
        );
        $duration = max(
            0,
            (int) ($row['duration'] ?? 0),
        );

        return new ChannelInboundItem(
            remoteId: $videoId,
            kind: 'video',
            title: self::nullableText(
                $row['title'] ?? null,
            ),
            text: trim((string) (
                $row['description'] ?? ''
            )),
            canonicalUrl: self::videoUrl(
                $row['video_url'] ?? null,
                $videoId,
            ),
            media: [[
                'type' => 'video',
                'video_id' => $videoId,
                'thumbnail_url' => $thumbnail,
                'duration' => $duration,
            ]],
            publishedAt: $publishedAt,
            updatedAt: null,
            payload: [
                'author_id' => is_array($row['author'] ?? null)
                    ? ($row['author']['id'] ?? null)
                    : null,
                'author_name' => is_array($row['author'] ?? null)
                    ? ($row['author']['name'] ?? null)
                    : null,
                'duration' => $duration,
                'hits' => max(
                    0,
                    (int) ($row['hits'] ?? 0),
                ),
                'is_paid' => self::boolValue(
                    $row['is_paid'] ?? false,
                ),
            ],
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array{timestamp:int,video_id:string}|null
     */
    private static function rowPoint(
        array $row,
    ): ?array {
        $videoId = self::videoId($row['id'] ?? null);
        $publishedAt = self::publishedAt($row);

        if ($videoId === null || $publishedAt === null) {
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
                '/^(\d+)\|([A-Fa-f0-9]{32})$/D',
                $cursor,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        return [
            'timestamp' => (int) $matches[1],
            'video_id' => strtolower($matches[2]),
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
            . strtolower($point['video_id']);
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
            strtolower($left['video_id']),
        ] <=> [
            $right['timestamp'],
            strtolower($right['video_id']),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function publishedAt(
        array $row,
    ): ?DateTimeImmutable {
        foreach ([
            'publication_ts',
            'created_ts',
        ] as $key) {
            $value = $row[$key] ?? null;

            if (is_int($value) || ctype_digit((string) $value)) {
                $timestamp = (int) $value;
                if ($timestamp > 0) {
                    return (new DateTimeImmutable(
                        '@' . $timestamp,
                    ))->setTimezone(
                        new \DateTimeZone('UTC'),
                    );
                }
            }

            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            try {
                return new DateTimeImmutable($text);
            } catch (\Exception) {
            }
        }

        return null;
    }

    private static function personId(
        string $value,
    ): ?int {
        $value = trim($value);

        if (
            preg_match('/^[1-9][0-9]{0,19}$/D', $value) !== 1
        ) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private static function videoId(
        mixed $value,
    ): ?string {
        $value = strtolower(
            trim((string) $value),
        );

        return preg_match(
            '/^[a-f0-9]{32}$/D',
            $value,
        ) === 1
            ? $value
            : null;
    }

    private static function videoUrl(
        mixed $value,
        string $videoId,
    ): string {
        $url = self::httpsUrl($value);

        if ($url !== null) {
            $parts = parse_url($url);
            $host = strtolower(
                (string) ($parts['host'] ?? '')
            );
            $path = (string) ($parts['path'] ?? '');

            if (
                in_array(
                    $host,
                    ['rutube.ru', 'www.rutube.ru'],
                    true,
                )
                && str_contains(
                    $path,
                    '/video/' . $videoId,
                )
            ) {
                return $url;
            }
        }

        return 'https://rutube.ru/video/'
            . rawurlencode($videoId)
            . '/';
    }

    private static function httpsUrl(
        mixed $value,
    ): ?string {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $parts = parse_url($value);

        return is_array($parts)
            && strtolower(
                (string) ($parts['scheme'] ?? '')
            ) === 'https'
            && trim(
                (string) ($parts['host'] ?? '')
            ) !== ''
            ? $value
            : null;
    }

    private static function nullableText(
        mixed $value,
    ): ?string {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function boolValue(
        mixed $value,
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(
            strtolower((string) $value),
            ['1', 'true', 'yes', 'on'],
            true,
        );
    }
}
