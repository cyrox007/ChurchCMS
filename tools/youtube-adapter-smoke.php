<?php

declare(strict_types=1);

use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ChannelCapability;
use ChurchCMS\Modules\Social\ChannelHttpClient;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\ChannelTransferHttpClient;
use ChurchCMS\Modules\Social\SocialConnection;
use ChurchCMS\Modules\Social\SocialConnectionService;
use ChurchCMS\Modules\Social\YoutubePublishingChannelAdapter;

require dirname(__DIR__) . '/core.php';

final class YoutubeSmokeHttpClient implements ChannelHttpClient
{
    public const CHANNEL_ID = 'UC1234567890123456789012';

    /** @var list<array<string,mixed>> */
    public array $calls = [];

    public function getJson(
        string $url,
        array $query = [],
        array $headers = [],
    ): array {
        $this->calls[] = compact('url', 'query', 'headers');

        if (
            $url === 'https://www.googleapis.com/youtube/v3/channels'
            && ($query['mine'] ?? null) === 'true'
        ) {
            if (
                ($headers['Authorization'] ?? null)
                    !== 'Bearer refreshed-access-token'
            ) {
                throw new RuntimeException(
                    'OAuth access token не передан в channels.list.'
                );
            }

            return self::ok([
                'items' => [[
                    'id' => self::CHANNEL_ID,
                ]],
            ]);
        }

        if (
            $url === 'https://www.googleapis.com/youtube/v3/channels'
            && ($query['id'] ?? null) === self::CHANNEL_ID
            && ($query['key'] ?? null) === 'youtube-api-key-smoke'
        ) {
            return self::ok([
                'items' => [[
                    'id' => self::CHANNEL_ID,
                    'snippet' => [
                        'title' => 'ChurchCMS channel',
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UU1234567890123456789012',
                        ],
                    ],
                ]],
            ]);
        }

        if (
            $url === 'https://www.googleapis.com/youtube/v3/playlistItems'
            && ($query['playlistId'] ?? null)
                === 'UU1234567890123456789012'
            && ($query['key'] ?? null) === 'youtube-api-key-smoke'
        ) {
            return self::ok([
                'items' => [[
                    'id' => 'playlist-video-1',
                    'snippet' => [
                        'channelId' => self::CHANNEL_ID,
                        'title' => 'Новое видео',
                        'description' => 'Описание видео',
                        'publishedAt' => '2026-10-03T10:00:00Z',
                        'resourceId' => [
                            'kind' => 'youtube#video',
                            'videoId' => 'video123456',
                        ],
                    ],
                    'contentDetails' => [
                        'videoId' => 'video123456',
                        'videoPublishedAt' => '2026-10-03T10:00:00Z',
                    ],
                    'status' => [
                        'privacyStatus' => 'public',
                    ],
                ]],
            ]);
        }

        throw new RuntimeException(
            'Неожиданный YouTube GET: ' . $url
        );
    }

    public function postJson(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        throw new RuntimeException(
            'YouTube smoke не ожидает JSON POST через ChannelHttpClient.'
        );
    }

    public function postForm(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        $this->calls[] = compact('url', 'payload', 'headers');

        if (
            $url !== 'https://oauth2.googleapis.com/token'
            || ($payload['grant_type'] ?? null) !== 'refresh_token'
            || ($payload['refresh_token'] ?? null) !== 'refresh-token-smoke'
            || ($payload['client_id'] ?? null) !== 'client-id-smoke'
            || ($payload['client_secret'] ?? null) !== 'client-secret-smoke'
        ) {
            throw new RuntimeException(
                'Google OAuth получил неверный refresh-запрос.'
            );
        }

        return self::ok([
            'access_token' => 'refreshed-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * @param array<string,mixed> $json
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    private static function ok(array $json): array
    {
        return [
            'status' => 200,
            'body' => json_encode(
                $json,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
            'json' => $json,
        ];
    }
}

final class YoutubeSmokeTransferHttpClient implements ChannelTransferHttpClient
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];

    private int $putNumber = 0;

    public function request(
        string $method,
        string $url,
        ?string $body = null,
        array $headers = [],
    ): array {
        $this->calls[] = compact(
            'method',
            'url',
            'body',
            'headers',
        );

        if (
            $method === 'POST'
            && str_starts_with(
                $url,
                'https://www.googleapis.com/upload/youtube/v3/videos',
            )
        ) {
            if (
                ($headers['Authorization'] ?? null)
                    !== 'Bearer refreshed-access-token'
                || ($headers['X-Upload-Content-Length'] ?? null)
                    !== '10'
                || ($headers['X-Upload-Content-Type'] ?? null)
                    !== 'video/mp4'
            ) {
                throw new RuntimeException(
                    'YouTube init upload получил неверные заголовки.'
                );
            }

            return self::response(
                200,
                [],
                [
                    'location' =>
                        'https://upload.googleapis.com/upload/youtube/v3/videos?upload_id=smoke',
                ],
            );
        }

        if (
            $method !== 'PUT'
            || !str_starts_with(
                $url,
                'https://upload.googleapis.com/',
            )
        ) {
            throw new RuntimeException(
                'Неожиданный запрос resumable upload.'
            );
        }

        $this->putNumber++;

        if ($this->putNumber === 1) {
            if (
                $body !== 'abcdef'
                || ($headers['Content-Range'] ?? null)
                    !== 'bytes 0-5/10'
            ) {
                throw new RuntimeException(
                    'Первый YouTube chunk сформирован неверно.'
                );
            }

            return self::response(
                308,
                [],
                ['range' => 'bytes=0-5'],
            );
        }

        if ($this->putNumber === 2) {
            if (
                $body !== 'ghij'
                || ($headers['Content-Range'] ?? null)
                    !== 'bytes 6-9/10'
            ) {
                throw new RuntimeException(
                    'Второй YouTube chunk сформирован неверно.'
                );
            }

            return self::response(
                200,
                ['id' => 'published123'],
            );
        }

        throw new RuntimeException(
            'YouTube smoke отправил лишний chunk.'
        );
    }

    /**
     * @param array<string,mixed> $json
     * @param array<string,string> $headers
     * @return array{
     *     status:int,
     *     body:string,
     *     json:array<string,mixed>|null,
     *     headers:array<string,string>
     * }
     */
    private static function response(
        int $status,
        array $json,
        array $headers = [],
    ): array {
        return [
            'status' => $status,
            'body' => $json === []
                ? ''
                : json_encode(
                    $json,
                    JSON_THROW_ON_ERROR,
                ),
            'json' => $json === [] ? null : $json,
            'headers' => $headers,
        ];
    }
}

final class YoutubeSmokeMediaCapability
{
    public YoutubeSmokeMediaService $transfer;

    public function __construct()
    {
        $this->transfer = new YoutubeSmokeMediaService();
    }

    public function service(): YoutubeSmokeMediaService
    {
        return $this->transfer;
    }

    public function source(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): object {
        if (
            $mediaPublicId !== 'media-video-smoke'
            || $siteKey !== 'parish-smoke'
        ) {
            throw new RuntimeException(
                'YouTube запросил неверный Media source.'
            );
        }

        return (object) [
            'mediaPublicId' => $mediaPublicId,
            'siteKey' => $siteKey,
            'mimeType' => 'video/mp4',
            'bytes' => 10,
            'sha256' => hash('sha256', 'abcdefghij'),
        ];
    }
}

final class YoutubeSmokeMediaService
{
    private ?object $transfer = null;

    public function find(
        string $mediaPublicId,
        string $providerId,
        string $targetKey,
        string $siteKey = 'default',
    ): ?object {
        return $this->transfer;
    }

    public function begin(
        object $source,
        string $providerId,
        string $targetKey,
        string $session,
        mixed $expiresAt = null,
    ): object {
        if (
            $providerId !== 'youtube'
            || !str_starts_with($targetKey, 'upload:')
            || !str_starts_with(
                $session,
                'https://upload.googleapis.com/',
            )
        ) {
            throw new RuntimeException(
                'YouTube создал некорректную resumable transfer.'
            );
        }

        $this->transfer = (object) [
            'status' => 'active',
            'uploadedBytes' => 0,
            'totalBytes' => 10,
            'mediaPublicId' => $source->mediaPublicId,
            'siteKey' => $source->siteKey,
            'session' => $session,
        ];

        return $this->transfer;
    }

    public function session(object $transfer): string
    {
        return (string) $transfer->session;
    }

    public function readNext(
        object $transfer,
        int $maxBytes,
    ): object {
        $offset = (int) $transfer->uploadedBytes;
        $data = $offset === 0 ? 'abcdef' : 'ghij';
        $remaining = 10 - $offset;
        $data = substr($data, 0, min(strlen($data), $remaining));

        return (object) [
            'offset' => $offset,
            'data' => $data,
            'nextOffset' => $offset + strlen($data),
            'totalBytes' => 10,
        ];
    }

    public function advance(
        object $transfer,
        object $chunk,
    ): object {
        $transfer->uploadedBytes = (int) $chunk->nextOffset;
        $this->transfer = $transfer;

        return $transfer;
    }

    public function complete(object $transfer): object
    {
        if ((int) $transfer->uploadedBytes !== 10) {
            throw new RuntimeException(
                'YouTube transfer завершён не на последнем байте.'
            );
        }

        $transfer->status = 'completed';
        $this->transfer = $transfer;

        return $transfer;
    }

    public function fail(object $transfer, string $error): object
    {
        $transfer->status = 'failed';
        $this->transfer = $transfer;

        return $transfer;
    }

    public function completed(): bool
    {
        return ($this->transfer->status ?? null) === 'completed';
    }
}

$registered = ChannelAdapterRegistry::get('youtube');
if (!$registered instanceof YoutubePublishingChannelAdapter) {
    fwrite(
        STDERR,
        "YouTube publishing adapter не зарегистрирован runtime.\n",
    );
    exit(1);
}

if (
    $registered->capabilities() !== [
        ChannelCapability::PUBLISH_VIDEO,
        ChannelCapability::IMPORT_VIDEO,
        ChannelCapability::POLLING,
    ]
) {
    fwrite(
        STDERR,
        "YouTube adapter объявил неверные возможности.\n",
    );
    exit(1);
}

$metadata = null;
foreach (
    SocialConnectionService::fromDatabase()->availableAdapters()
    as $adapter
) {
    if (($adapter['id'] ?? null) === 'youtube') {
        $metadata = $adapter;
        break;
    }
}

if (
    !is_array($metadata)
    || ($metadata['can_publish'] ?? false) !== true
    || ($metadata['can_import'] ?? false) !== true
) {
    fwrite(
        STDERR,
        "Мастер подключений не распознал двусторонний YouTube.\n",
    );
    exit(1);
}

$http = new YoutubeSmokeHttpClient();
$transferHttp = new YoutubeSmokeTransferHttpClient();
$media = new YoutubeSmokeMediaCapability();
$adapter = new YoutubePublishingChannelAdapter(
    $http,
    $transferHttp,
    $media,
);

$inboundTest = $adapter->testConnection(
    YoutubeSmokeHttpClient::CHANNEL_ID,
    'youtube-api-key-smoke',
);
if (!$inboundTest->success) {
    fwrite(
        STDERR,
        "Старый API key больше не проходит inbound-проверку YouTube.\n",
    );
    exit(1);
}

$connection = new SocialConnection(
    id: 4,
    publicId: 'youtube-smoke-connection',
    provider: 'youtube',
    name: 'YouTube smoke',
    targetRef: YoutubeSmokeHttpClient::CHANNEL_ID,
    tokenEncrypted: '',
    settings: [],
    enabled: true,
    outboundEnabled: true,
    inboundEnabled: true,
    inboundPolicy: 'review',
    connectionKind: 'video',
    createdAt: new DateTimeImmutable('2026-10-03 00:00:00 UTC'),
    updatedAt: new DateTimeImmutable('2026-10-03 00:00:00 UTC'),
);

$batch = $adapter->pull(
    $connection,
    'youtube-api-key-smoke',
    null,
    1,
);
if (
    count($batch->items) !== 1
    || $batch->items[0]->remoteId !== 'video123456'
) {
    fwrite(
        STDERR,
        "Inbound YouTube polling сломан после добавления upload.\n",
    );
    exit(1);
}

$credentials = json_encode(
    [
        'api_key' => 'youtube-api-key-smoke',
        'refresh_token' => 'refresh-token-smoke',
        'client_id' => 'client-id-smoke',
        'client_secret' => 'client-secret-smoke',
    ],
    JSON_THROW_ON_ERROR,
);

$outboundTest = $adapter->testConnection(
    YoutubeSmokeHttpClient::CHANNEL_ID,
    $credentials,
);
if (!$outboundTest->success) {
    fwrite(
        STDERR,
        "OAuth-проверка исходящего YouTube не прошла.\n",
    );
    exit(1);
}

$result = $adapter->publish(
    $connection,
    $credentials,
    new ChannelOutboundItem(
        sourceId: 'publication-smoke',
        kind: 'video',
        title: 'Видео прихода',
        text: 'Описание публикации',
        canonicalUrl: 'https://church.example/publications/video-smoke',
        media: [[
            'type' => 'video',
            'public_id' => 'media-video-smoke',
            'site_key' => 'parish-smoke',
            'mime_type' => 'video/mp4',
            'bytes' => 10,
            'sha256' => hash('sha256', 'abcdefghij'),
        ]],
    ),
);

if (
    !$result->success
    || $result->remoteId !== 'published123'
    || $result->remoteUrl
        !== 'https://www.youtube.com/watch?v=published123'
    || !$media->transfer->completed()
) {
    fwrite(
        STDERR,
        "YouTube resumable upload не завершён корректно: "
        . (string) $result->error
        . "\n",
    );
    exit(1);
}

echo "YouTube inbound/outbound adapter smoke OK\n";
