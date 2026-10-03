<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\ModuleRuntimeLoader;
use RuntimeException;
use Throwable;

final class YoutubePublishingChannelAdapter implements
    ChannelAdapter,
    ChannelConnectionTester
{
    private const API_BASE = 'https://www.googleapis.com/youtube/v3';
    private const UPLOAD_URL =
        'https://www.googleapis.com/upload/youtube/v3/videos'
        . '?uploadType=resumable&part=snippet,status';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CHUNK_BYTES = 8388608;

    private readonly ChannelHttpClient $http;
    private readonly ChannelTransferHttpClient $transferHttp;
    private readonly YoutubeChannelAdapter $inbound;

    public function __construct(
        ?ChannelHttpClient $http = null,
        ?ChannelTransferHttpClient $transferHttp = null,
        private readonly ?object $mediaCapability = null,
    ) {
        $this->http = $http ?? new NativeHttpClient();
        $this->transferHttp = $transferHttp
            ?? new NativeTransferHttpClient();
        $this->inbound = new YoutubeChannelAdapter(
            $this->http,
        );
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
            ChannelCapability::PUBLISH_VIDEO,
            ChannelCapability::IMPORT_VIDEO,
            ChannelCapability::POLLING,
        ];
    }

    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult {
        try {
            $secret = self::credentials($credentials);
        } catch (Throwable) {
            return new ChannelConnectionTestResult(
                false,
                'Некорректный секрет подключения YouTube.',
            );
        }

        $inboundTest = $this->inbound->testConnection(
            $targetRef,
            $secret['api_key'],
            $settings,
        );

        if (!$inboundTest->success) {
            return $inboundTest;
        }

        if (!self::canUpload($secret)) {
            return new ChannelConnectionTestResult(
                true,
                'Публичный канал YouTube доступен для входящей синхронизации. '
                . 'Для исходящей загрузки добавьте OAuth-параметры.',
            );
        }

        try {
            $accessToken = $this->accessToken($secret);

            if (!$this->ownsChannel(
                trim($targetRef),
                $accessToken,
            )) {
                return new ChannelConnectionTestResult(
                    false,
                    'OAuth-учётная запись YouTube не владеет указанным каналом.',
                );
            }
        } catch (Throwable) {
            return new ChannelConnectionTestResult(
                false,
                'YouTube не подтвердил OAuth-доступ к указанному каналу.',
            );
        }

        return new ChannelConnectionTestResult(
            true,
            'Канал YouTube доступен для синхронизации и загрузки видео.',
        );
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        try {
            $secret = self::credentials($credentials);
            if (!self::canUpload($secret)) {
                throw new RuntimeException(
                    'Для загрузки в YouTube нужен access token либо refresh token с client credentials.'
                );
            }

            $accessToken = $this->accessToken($secret);
            if (!$this->ownsChannel(
                trim($connection->targetRef),
                $accessToken,
            )) {
                throw new RuntimeException(
                    'OAuth-учётная запись YouTube не владеет целевым каналом.'
                );
            }

            $video = self::video($item);
            $capability = $this->mediaCapability();
            $service = $capability->service();
            $source = $capability->source(
                $video['public_id'],
                $video['site_key'],
            );

            if (
                !is_object($source)
                || !isset(
                    $source->bytes,
                    $source->mimeType,
                    $source->mediaPublicId,
                    $source->siteKey,
                )
                || (int) $source->bytes <= 0
                || !str_starts_with(
                    strtolower((string) $source->mimeType),
                    'video/',
                )
            ) {
                throw new RuntimeException(
                    'Media не вернул пригодный бинарный источник видео.'
                );
            }

            $targetKey = self::targetKey(
                $connection,
                $item,
            );
            $transfer = $service->find(
                (string) $source->mediaPublicId,
                'youtube',
                $targetKey,
                (string) $source->siteKey,
            );

            if (
                is_object($transfer)
                && ($transfer->status ?? null) === 'completed'
            ) {
                $recovered = $this->recoverCompleted(
                    $service,
                    $transfer,
                    $accessToken,
                );

                if ($recovered !== null) {
                    return self::success($recovered);
                }

                throw new RuntimeException(
                    'YouTube upload уже отмечен завершённым, но remote video ID восстановить не удалось.'
                );
            }

            if (
                !is_object($transfer)
                || ($transfer->status ?? null) !== 'active'
            ) {
                $session = $this->startSession(
                    $accessToken,
                    $source,
                    $connection,
                    $item,
                );
                $transfer = $service->begin(
                    $source,
                    'youtube',
                    $targetKey,
                    $session,
                );
            } else {
                $recovered = $this->resumeRemoteState(
                    $service,
                    $transfer,
                    $accessToken,
                );

                if ($recovered !== null) {
                    return self::success($recovered);
                }
            }

            $videoId = $this->uploadChunks(
                $service,
                $transfer,
                $accessToken,
            );

            return self::success($videoId);
        } catch (Throwable $error) {
            return new ChannelPublishResult(
                false,
                error: self::safeError($error),
            );
        }
    }

    public function pull(
        SocialConnection $connection,
        string $credentials,
        ?string $cursor,
        int $limit = 50,
    ): ChannelPullBatch {
        $secret = self::credentials($credentials);

        return $this->inbound->pull(
            $connection,
            $secret['api_key'],
            $cursor,
            $limit,
        );
    }

    private function mediaCapability(): object
    {
        $capability = $this->mediaCapability
            ?? ModuleRuntimeLoader::capability(
                'media',
                'media.resumable-upload',
            );

        if (
            !is_object($capability)
            || !method_exists($capability, 'service')
            || !method_exists($capability, 'source')
        ) {
            throw new RuntimeException(
                'Media resumable upload недоступен для YouTube.'
            );
        }

        return $capability;
    }

    /**
     * @param array{
     *     api_key:string,
     *     access_token:string,
     *     refresh_token:string,
     *     client_id:string,
     *     client_secret:string
     * } $secret
     */
    private function accessToken(array $secret): string
    {
        if (
            $secret['refresh_token'] !== ''
            && $secret['client_id'] !== ''
            && $secret['client_secret'] !== ''
        ) {
            $response = $this->http->postForm(
                self::TOKEN_URL,
                [
                    'client_id' => $secret['client_id'],
                    'client_secret' => $secret['client_secret'],
                    'refresh_token' => $secret['refresh_token'],
                    'grant_type' => 'refresh_token',
                ],
            );
            $json = $response['json'] ?? null;
            $token = is_array($json)
                ? trim((string) ($json['access_token'] ?? ''))
                : '';

            if (
                (int) ($response['status'] ?? 0) < 200
                || (int) ($response['status'] ?? 0) >= 300
                || $token === ''
            ) {
                throw new RuntimeException(
                    'Google OAuth не обновил access token.'
                );
            }

            return $token;
        }

        if ($secret['access_token'] === '') {
            throw new RuntimeException(
                'В секрете YouTube отсутствует OAuth access token.'
            );
        }

        return $secret['access_token'];
    }

    private function ownsChannel(
        string $channelId,
        string $accessToken,
    ): bool {
        if ($channelId === '') {
            return false;
        }

        $response = $this->http->getJson(
            self::API_BASE . '/channels',
            [
                'part' => 'id',
                'mine' => 'true',
                'maxResults' => 50,
            ],
            [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        );

        if (
            (int) ($response['status'] ?? 0) < 200
            || (int) ($response['status'] ?? 0) >= 300
            || !is_array($response['json'] ?? null)
        ) {
            throw new RuntimeException(
                'YouTube Data API отклонил проверку владельца канала.'
            );
        }

        $items = $response['json']['items'] ?? null;
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $row) {
            if (
                is_array($row)
                && trim((string) ($row['id'] ?? '')) === $channelId
            ) {
                return true;
            }
        }

        return false;
    }

    private function startSession(
        string $accessToken,
        object $source,
        SocialConnection $connection,
        ChannelOutboundItem $item,
    ): string {
        $privacy = strtolower(trim((string) (
            $connection->settings['youtube_privacy'] ?? 'unlisted'
        )));
        if (!in_array(
            $privacy,
            ['private', 'unlisted', 'public'],
            true,
        )) {
            $privacy = 'unlisted';
        }

        $categoryId = trim((string) (
            $connection->settings['youtube_category_id'] ?? '22'
        ));
        if (preg_match('/^\d{1,4}$/D', $categoryId) !== 1) {
            $categoryId = '22';
        }

        $payload = [
            'snippet' => [
                'title' => self::title($item),
                'description' => self::description($item),
                'categoryId' => $categoryId,
            ],
            'status' => [
                'privacyStatus' => $privacy,
            ],
        ];
        $body = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES,
        );

        $response = $this->transferHttp->request(
            'POST',
            self::UPLOAD_URL,
            $body,
            [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Length' => (string) strlen($body),
                'X-Upload-Content-Length' => (string) $source->bytes,
                'X-Upload-Content-Type' => (string) $source->mimeType,
            ],
        );

        $status = (int) ($response['status'] ?? 0);
        $headers = $response['headers'] ?? null;
        $session = is_array($headers)
            ? trim((string) ($headers['location'] ?? ''))
            : '';

        if (
            $status < 200
            || $status >= 300
            || !self::validSessionUrl($session)
        ) {
            throw new RuntimeException(
                'YouTube не создал resumable upload session.'
            );
        }

        return $session;
    }

    private function uploadChunks(
        object $service,
        object $transfer,
        string $accessToken,
    ): string {
        while (
            ($transfer->status ?? null) === 'active'
            && (int) ($transfer->uploadedBytes ?? 0)
                < (int) ($transfer->totalBytes ?? 0)
        ) {
            $chunk = $service->readNext(
                $transfer,
                self::CHUNK_BYTES,
            );
            $length = strlen((string) $chunk->data);

            if ($length <= 0) {
                throw new RuntimeException(
                    'Media вернул пустой chunk до завершения YouTube upload.'
                );
            }

            $start = (int) $chunk->offset;
            $end = (int) $chunk->nextOffset - 1;
            $total = (int) $chunk->totalBytes;
            $session = $service->session($transfer);

            if (!self::validSessionUrl($session)) {
                throw new RuntimeException(
                    'Сохранённая YouTube upload session имеет некорректный URL.'
                );
            }

            $response = $this->transferHttp->request(
                'PUT',
                $session,
                (string) $chunk->data,
                [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => self::transferMimeType($transfer),
                    'Content-Length' => (string) $length,
                    'Content-Range' => 'bytes '
                        . $start
                        . '-'
                        . $end
                        . '/'
                        . $total,
                ],
            );
            $status = (int) ($response['status'] ?? 0);

            if ($status === 308) {
                $remoteOffset = self::remoteOffset($response);
                if ($remoteOffset < (int) $chunk->nextOffset) {
                    throw new RuntimeException(
                        'YouTube не подтвердил отправленный chunk полностью.'
                    );
                }

                $transfer = $this->synchronizeTransfer(
                    $service,
                    $transfer,
                    $remoteOffset,
                );
                continue;
            }

            if ($status >= 200 && $status < 300) {
                $videoId = self::videoId($response);
                if ($videoId === null) {
                    throw new RuntimeException(
                        'YouTube завершил upload без video ID.'
                    );
                }

                $transfer = $this->synchronizeTransfer(
                    $service,
                    $transfer,
                    $total,
                );
                $service->complete($transfer);

                return $videoId;
            }

            throw new RuntimeException(
                'YouTube отклонил chunk видео, HTTP '
                . $status
                . '.'
            );
        }

        throw new RuntimeException(
            'YouTube upload завершился без remote video ID.'
        );
    }

    private function resumeRemoteState(
        object $service,
        object $transfer,
        string $accessToken,
    ): ?string {
        $session = $service->session($transfer);
        if (!self::validSessionUrl($session)) {
            throw new RuntimeException(
                'Сохранённая YouTube upload session имеет некорректный URL.'
            );
        }

        $response = $this->transferHttp->request(
            'PUT',
            $session,
            '',
            [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Length' => '0',
                'Content-Range' => 'bytes */'
                    . (int) $transfer->totalBytes,
            ],
        );
        $status = (int) ($response['status'] ?? 0);

        if ($status === 308) {
            $remoteOffset = self::remoteOffset($response);
            if ($remoteOffset < (int) $transfer->uploadedBytes) {
                throw new RuntimeException(
                    'YouTube сообщает смещение меньше локально подтверждённого.'
                );
            }

            $this->synchronizeTransfer(
                $service,
                $transfer,
                $remoteOffset,
            );

            return null;
        }

        if ($status >= 200 && $status < 300) {
            $videoId = self::videoId($response);
            if ($videoId === null) {
                throw new RuntimeException(
                    'YouTube подтвердил завершение session без video ID.'
                );
            }

            $transfer = $this->synchronizeTransfer(
                $service,
                $transfer,
                (int) $transfer->totalBytes,
            );
            $service->complete($transfer);

            return $videoId;
        }

        throw new RuntimeException(
            'YouTube не позволил продолжить resumable upload, HTTP '
            . $status
            . '.'
        );
    }

    private function recoverCompleted(
        object $service,
        object $transfer,
        string $accessToken,
    ): ?string {
        try {
            $session = $service->session($transfer);
            if (!self::validSessionUrl($session)) {
                return null;
            }

            $response = $this->transferHttp->request(
                'PUT',
                $session,
                '',
                [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Length' => '0',
                    'Content-Range' => 'bytes */'
                        . (int) $transfer->totalBytes,
                ],
            );

            return self::videoId($response);
        } catch (Throwable) {
            return null;
        }
    }

    private function synchronizeTransfer(
        object $service,
        object $transfer,
        int $targetOffset,
    ): object {
        $total = (int) ($transfer->totalBytes ?? 0);
        $targetOffset = min($total, max(0, $targetOffset));

        while ((int) $transfer->uploadedBytes < $targetOffset) {
            $remaining = $targetOffset
                - (int) $transfer->uploadedBytes;
            $chunk = $service->readNext(
                $transfer,
                min(self::CHUNK_BYTES, $remaining),
            );

            if (
                (int) $chunk->nextOffset
                    > $targetOffset
                || (int) $chunk->nextOffset
                    <= (int) $chunk->offset
            ) {
                throw new RuntimeException(
                    'Media не смог синхронизировать resumable offset.'
                );
            }

            $transfer = $service->advance(
                $transfer,
                $chunk,
            );
        }

        return $transfer;
    }

    /**
     * @return array{public_id:string,site_key:string}
     */
    private static function video(
        ChannelOutboundItem $item,
    ): array {
        foreach ($item->media as $media) {
            if (!is_array($media)) {
                continue;
            }

            $publicId = trim((string) (
                $media['public_id'] ?? ''
            ));

            if (
                ($media['type'] ?? null) === 'video'
                && $publicId !== ''
            ) {
                return [
                    'public_id' => $publicId,
                    'site_key' => trim((string) (
                        $media['site_key'] ?? 'default'
                    )) ?: 'default',
                ];
            }
        }

        throw new RuntimeException(
            'Для публикации YouTube не найдено связанное видео Media.'
        );
    }

    /**
     * @return array{
     *     api_key:string,
     *     access_token:string,
     *     refresh_token:string,
     *     client_id:string,
     *     client_secret:string
     * }
     */
    private static function credentials(
        string $credentials,
    ): array {
        $credentials = trim($credentials);
        if ($credentials === '') {
            throw new RuntimeException(
                'Секрет подключения YouTube пуст.'
            );
        }

        if (!str_starts_with($credentials, '{')) {
            return [
                'api_key' => $credentials,
                'access_token' => '',
                'refresh_token' => '',
                'client_id' => '',
                'client_secret' => '',
            ];
        }

        try {
            $decoded = json_decode(
                $credentials,
                true,
                32,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $error) {
            throw new RuntimeException(
                'Секрет YouTube содержит некорректный JSON.',
                0,
                $error,
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Секрет YouTube должен быть JSON-объектом.'
            );
        }

        $result = [
            'api_key' => trim((string) ($decoded['api_key'] ?? '')),
            'access_token' => trim((string) ($decoded['access_token'] ?? '')),
            'refresh_token' => trim((string) ($decoded['refresh_token'] ?? '')),
            'client_id' => trim((string) ($decoded['client_id'] ?? '')),
            'client_secret' => trim((string) ($decoded['client_secret'] ?? '')),
        ];

        if ($result['api_key'] === '') {
            throw new RuntimeException(
                'В JSON-секрете YouTube отсутствует api_key для polling и проверки канала.'
            );
        }

        $refreshFields = [
            $result['refresh_token'],
            $result['client_id'],
            $result['client_secret'],
        ];
        $refreshCount = count(array_filter(
            $refreshFields,
            static fn(string $value): bool => $value !== '',
        ));

        if ($refreshCount !== 0 && $refreshCount !== 3) {
            throw new RuntimeException(
                'Для обновления YouTube OAuth нужны refresh_token, client_id и client_secret вместе.'
            );
        }

        return $result;
    }

    /**
     * @param array{
     *     api_key:string,
     *     access_token:string,
     *     refresh_token:string,
     *     client_id:string,
     *     client_secret:string
     * } $secret
     */
    private static function canUpload(array $secret): bool
    {
        return $secret['access_token'] !== ''
            || (
                $secret['refresh_token'] !== ''
                && $secret['client_id'] !== ''
                && $secret['client_secret'] !== ''
            );
    }

    private static function targetKey(
        SocialConnection $connection,
        ChannelOutboundItem $item,
    ): string {
        return 'upload:' . substr(
            hash(
                'sha256',
                $connection->publicId
                . '|'
                . $item->sourceId,
            ),
            0,
            64,
        );
    }

    private static function transferMimeType(
        object $transfer,
    ): string {
        $capability = ModuleRuntimeLoader::capability(
            'media',
            'media.resumable-upload',
        );

        if (
            is_object($capability)
            && method_exists($capability, 'source')
        ) {
            try {
                $source = $capability->source(
                    (string) $transfer->mediaPublicId,
                    (string) $transfer->siteKey,
                );
                $mime = trim((string) (
                    $source->mimeType ?? ''
                ));
                if (str_starts_with(strtolower($mime), 'video/')) {
                    return $mime;
                }
            } catch (Throwable) {
                // Источник повторно проверит Media service при чтении chunk.
            }
        }

        return 'application/octet-stream';
    }

    /**
     * @param array<string,mixed> $response
     */
    private static function remoteOffset(array $response): int
    {
        $headers = $response['headers'] ?? null;
        $range = is_array($headers)
            ? trim((string) ($headers['range'] ?? ''))
            : '';

        if ($range === '') {
            return 0;
        }

        if (
            preg_match(
                '/^bytes=0-(\d+)$/D',
                $range,
                $match,
            ) !== 1
        ) {
            throw new RuntimeException(
                'YouTube вернул некорректный Range resumable upload.'
            );
        }

        return (int) $match[1] + 1;
    }

    /**
     * @param array<string,mixed> $response
     */
    private static function videoId(array $response): ?string
    {
        $status = (int) ($response['status'] ?? 0);
        $json = $response['json'] ?? null;
        $videoId = is_array($json)
            ? trim((string) ($json['id'] ?? ''))
            : '';

        if (
            $status >= 200
            && $status < 300
            && preg_match(
                '/^[A-Za-z0-9_-]{6,32}$/D',
                $videoId,
            ) === 1
        ) {
            return $videoId;
        }

        return null;
    }

    private static function validSessionUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        return $scheme === 'https'
            && !isset($parts['user'], $parts['pass'])
            && (
                $host === 'googleapis.com'
                || str_ends_with($host, '.googleapis.com')
            );
    }

    private static function title(
        ChannelOutboundItem $item,
    ): string {
        $title = trim($item->title);
        if ($title === '') {
            $title = 'Видео ChurchCMS';
        }

        return mb_substr($title, 0, 100);
    }

    private static function description(
        ChannelOutboundItem $item,
    ): string {
        $parts = [];
        $text = trim($item->text);
        $url = trim($item->canonicalUrl);

        if ($text !== '') {
            $parts[] = $text;
        }
        if ($url !== '') {
            $parts[] = $url;
        }

        return mb_substr(
            implode("\n\n", $parts),
            0,
            5000,
        );
    }

    private static function success(
        string $videoId,
    ): ChannelPublishResult {
        return new ChannelPublishResult(
            true,
            remoteId: $videoId,
            remoteUrl: 'https://www.youtube.com/watch?v='
                . rawurlencode($videoId),
        );
    }

    private static function safeError(Throwable $error): string
    {
        $message = str_replace(
            ["\r", "\n", "\0"],
            [' ', ' ', ''],
            trim($error->getMessage()),
        );

        if ($message === '') {
            $message = $error::class;
        }

        return mb_substr($message, 0, 500);
    }
}
