<?php

declare(strict_types=1);

use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ChannelCapability;
use ChurchCMS\Modules\Social\ChannelHttpClient;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\SocialConnection;
use ChurchCMS\Modules\Social\SocialConnectionService;
use ChurchCMS\Modules\Social\YoutubeChannelAdapter;

require dirname(__DIR__) . '/core.php';

final class YoutubeSmokeHttpClient implements ChannelHttpClient
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];

    private const CHANNEL_ID = 'UC1234567890123456789012';

    public function getJson(
        string $url,
        array $query = [],
        array $headers = [],
    ): array {
        $this->calls[] = [
            'url' => $url,
            'query' => $query,
            'headers' => $headers,
        ];

        if (($query['key'] ?? null) !== 'youtube-api-key-smoke') {
            throw new \RuntimeException(
                'YouTube API key не передан в запрос.'
            );
        }

        if (
            $url
                === 'https://www.googleapis.com/youtube/v3/channels'
        ) {
            if (
                ($query['id'] ?? null) !== self::CHANNEL_ID
                || ($query['part'] ?? null)
                    !== 'id,snippet,contentDetails'
            ) {
                throw new \RuntimeException(
                    'YouTube channels.list получил неверные параметры.'
                );
            }

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
            $url
                === 'https://www.googleapis.com/youtube/v3/playlistItems'
        ) {
            if (
                ($query['playlistId'] ?? null)
                    !== 'UU1234567890123456789012'
                || ($query['maxResults'] ?? null) !== 50
                || ($query['part'] ?? null)
                    !== 'snippet,contentDetails,status'
            ) {
                throw new \RuntimeException(
                    'YouTube playlistItems.list получил неверные параметры.'
                );
            }

            return self::ok([
                'items' => [
                    self::item(
                        id: 'playlist-new-2',
                        videoId: 'newvideo002',
                        publishedAt: '2026-09-30T07:00:00Z',
                        title: 'Новое видео 2',
                        description: 'Описание 2',
                        thumbnail: 'https://img.youtube.test/new2.jpg',
                    ),
                    self::item(
                        id: 'playlist-old',
                        videoId: 'oldvideo001',
                        publishedAt: '2026-09-30T05:00:00Z',
                        title: 'Старое видео',
                        description: 'Старое описание',
                        thumbnail: 'https://img.youtube.test/old.jpg',
                    ),
                    self::item(
                        id: 'playlist-new-1',
                        videoId: 'newvideo001',
                        publishedAt: '2026-09-30T06:00:00Z',
                        title: 'Новое видео 1',
                        description: 'Описание 1',
                        thumbnail: 'https://img.youtube.test/new1.jpg',
                    ),
                    [
                        'id' => 'playlist-foreign',
                        'snippet' => [
                            'channelId' => 'UC9999999999999999999999',
                            'title' => 'Чужой канал',
                            'description' => '',
                            'publishedAt' => '2026-09-30T08:00:00Z',
                            'resourceId' => [
                                'kind' => 'youtube#video',
                                'videoId' => 'foreign001',
                            ],
                        ],
                        'contentDetails' => [
                            'videoId' => 'foreign001',
                            'videoPublishedAt' => '2026-09-30T08:00:00Z',
                        ],
                        'status' => [
                            'privacyStatus' => 'public',
                        ],
                    ],
                ],
            ]);
        }

        throw new \RuntimeException(
            'Неожиданный YouTube URL: ' . $url
        );
    }

    public function postJson(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        throw new \RuntimeException(
            'Inbound YouTube adapter не должен использовать POST.'
        );
    }

    public function postForm(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        throw new \RuntimeException(
            'Inbound YouTube adapter не должен использовать form POST.'
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function item(
        string $id,
        string $videoId,
        string $publishedAt,
        string $title,
        string $description,
        string $thumbnail,
    ): array {
        return [
            'id' => $id,
            'snippet' => [
                'channelId' => self::CHANNEL_ID,
                'title' => $title,
                'description' => $description,
                'publishedAt' => $publishedAt,
                'position' => 0,
                'resourceId' => [
                    'kind' => 'youtube#video',
                    'videoId' => $videoId,
                ],
                'thumbnails' => [
                    'default' => [
                        'url' => $thumbnail . '?small=1',
                        'width' => 120,
                        'height' => 90,
                    ],
                    'high' => [
                        'url' => $thumbnail,
                        'width' => 480,
                        'height' => 360,
                    ],
                ],
            ],
            'contentDetails' => [
                'videoId' => $videoId,
                'videoPublishedAt' => $publishedAt,
            ],
            'status' => [
                'privacyStatus' => 'public',
            ],
        ];
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

$registered = ChannelAdapterRegistry::get('youtube');
if (!$registered instanceof YoutubeChannelAdapter) {
    fwrite(STDERR, "YouTube adapter не зарегистрирован runtime.\n");
    exit(1);
}

if (
    $registered->capabilities()
        !== [
            ChannelCapability::IMPORT_VIDEO,
            ChannelCapability::POLLING,
        ]
) {
    fwrite(
        STDERR,
        "YouTube adapter объявил неожиданные capabilities.\n",
    );
    exit(1);
}

$service = SocialConnectionService::fromDatabase();
$metadata = null;

foreach ($service->availableAdapters() as $adapter) {
    if (($adapter['id'] ?? null) === 'youtube') {
        $metadata = $adapter;
        break;
    }
}

if (
    !is_array($metadata)
    || ($metadata['can_publish'] ?? true) !== false
    || ($metadata['can_import'] ?? false) !== true
) {
    fwrite(
        STDERR,
        "Мастер не распознал YouTube как inbound-only адаптер.\n",
    );
    exit(1);
}

$http = new YoutubeSmokeHttpClient();
$adapter = new YoutubeChannelAdapter($http);
$channelId = 'UC1234567890123456789012';

$test = $adapter->testConnection(
    $channelId,
    'youtube-api-key-smoke',
);
if (!$test->success) {
    fwrite(
        STDERR,
        "YouTube testConnection не прошёл: "
        . (string) $test->message
        . "\n",
    );
    exit(1);
}

$invalid = $adapter->testConnection(
    'not-a-channel',
    'youtube-api-key-smoke',
);
if ($invalid->success) {
    fwrite(
        STDERR,
        "YouTube testConnection принял неверный channel ID.\n",
    );
    exit(1);
}

$now = new \DateTimeImmutable('2026-09-30 00:00:00 UTC');
$connection = new SocialConnection(
    id: 4,
    publicId: 'youtube-smoke-connection',
    provider: 'youtube',
    name: 'YouTube smoke',
    targetRef: $channelId,
    tokenEncrypted: '',
    settings: [],
    enabled: true,
    outboundEnabled: false,
    inboundEnabled: true,
    inboundPolicy: 'review',
    connectionKind: 'video',
    createdAt: $now,
    updatedAt: $now,
);

$cursor = (new \DateTimeImmutable(
    '2026-09-30T05:00:00Z',
))->getTimestamp() . '|oldvideo001';

$batch = $adapter->pull(
    $connection,
    'youtube-api-key-smoke',
    $cursor,
    2,
);

if (
    count($batch->items) !== 2
    || $batch->items[0]->remoteId !== 'newvideo001'
    || $batch->items[1]->remoteId !== 'newvideo002'
    || $batch->nextCursor
        !== (
            new \DateTimeImmutable(
                '2026-09-30T07:00:00Z',
            )
        )->getTimestamp()
        . '|newvideo002'
) {
    fwrite(
        STDERR,
        "YouTube polling неверно обработал cursor или порядок видео.\n",
    );
    exit(1);
}

$first = $batch->items[0];

if (
    $first->kind !== 'video'
    || $first->title !== 'Новое видео 1'
    || $first->text !== 'Описание 1'
    || $first->canonicalUrl
        !== 'https://www.youtube.com/watch?v=newvideo001'
    || count($first->media) !== 1
    || ($first->media[0]['thumbnail_url'] ?? null)
        !== 'https://img.youtube.test/new1.jpg'
) {
    fwrite(
        STDERR,
        "YouTube video нормализовано некорректно.\n",
    );
    exit(1);
}

$publish = $adapter->publish(
    $connection,
    'youtube-api-key-smoke',
    new ChannelOutboundItem(
        sourceId: 'source',
        kind: 'video',
        title: 'Видео',
        text: '',
        canonicalUrl: '',
    ),
);

if ($publish->success) {
    fwrite(
        STDERR,
        "Inbound-only YouTube adapter ошибочно подтвердил outbound.\n",
    );
    exit(1);
}

echo "YouTube inbound adapter smoke OK\n";
