<?php

declare(strict_types=1);

use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ChannelCapability;
use ChurchCMS\Modules\Social\ChannelHttpClient;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\SocialConnection;
use ChurchCMS\Modules\Social\VkChannelAdapter;

require dirname(__DIR__) . '/core.php';

final class VkSmokeHttpClient implements ChannelHttpClient
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];

    public int $adminLevel = 3;

    public function getJson(
        string $url,
        array $query = [],
        array $headers = [],
    ): array {
        throw new \RuntimeException(
            'VK adapter не должен использовать GET.'
        );
    }

    public function postJson(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        throw new \RuntimeException(
            'VK adapter не должен использовать JSON POST.'
        );
    }

    public function postForm(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        $this->calls[] = [
            'url' => $url,
            'payload' => $payload,
        ];

        if (
            ($payload['access_token'] ?? null)
                !== 'vk-user-token-smoke'
            || ($payload['v'] ?? null) !== '5.199'
        ) {
            throw new \RuntimeException(
                'VK API вызван без access_token или версии 5.199.'
            );
        }

        if (str_ends_with($url, '/groups.getById')) {
            if (
                ($payload['group_id'] ?? null)
                    !== 'parish_vk'
            ) {
                throw new \RuntimeException(
                    'groups.getById получил неверный targetRef.'
                );
            }

            return self::ok([
                'groups' => [[
                    'id' => 12345,
                    'name' => 'Приход',
                    'screen_name' => 'parish_vk',
                    'is_admin' => 1,
                    'admin_level' => $this->adminLevel,
                    'can_post' => 1,
                ]],
                'profiles' => [],
            ]);
        }

        if (str_ends_with($url, '/wall.post')) {
            if (
                ($payload['owner_id'] ?? null) !== -12345
                || ($payload['from_group'] ?? null) !== 1
            ) {
                throw new \RuntimeException(
                    'wall.post получил неверного владельца.'
                );
            }

            if (
                !isset($payload['guid'])
                || !is_string($payload['guid'])
                || strlen($payload['guid']) !== 32
            ) {
                throw new \RuntimeException(
                    'wall.post не получил стабильный guid.'
                );
            }

            $message = (string) ($payload['message'] ?? '');
            if (
                !str_contains($message, 'VK smoke text')
                || !str_contains(
                    $message,
                    'https://church.example/publications/vk-smoke',
                )
            ) {
                throw new \RuntimeException(
                    'wall.post не получил текст или canonical URL.'
                );
            }

            return self::ok([
                'post_id' => 77,
            ]);
        }

        if (str_ends_with($url, '/wall.get')) {
            if (
                ($payload['domain'] ?? null) !== -12345
                || ($payload['filter'] ?? null) !== 'owner'
                || ($payload['count'] ?? null) !== 50
            ) {
                throw new \RuntimeException(
                    'wall.get получил неверные параметры.'
                );
            }

            return self::ok([
                'count' => 4,
                'items' => [
                    [
                        'id' => 11,
                        'owner_id' => -12345,
                        'from_id' => -12345,
                        'date' => 101,
                        'text' => 'Новый пост',
                        'attachments' => [[
                            'type' => 'photo',
                            'photo' => [
                                'id' => 501,
                                'owner_id' => -12345,
                                'date' => 101,
                                'sizes' => [
                                    [
                                        'type' => 'm',
                                        'url' => 'https://vk.example/m.jpg',
                                        'width' => 130,
                                        'height' => 100,
                                    ],
                                    [
                                        'type' => 'x',
                                        'url' => 'https://vk.example/x.jpg',
                                        'width' => 604,
                                        'height' => 400,
                                    ],
                                ],
                            ],
                        ]],
                    ],
                    [
                        'id' => 10,
                        'owner_id' => -12345,
                        'from_id' => -12345,
                        'date' => 90,
                        'edited' => 102,
                        'text' => 'Исправленный пост',
                        'attachments' => [
                            [
                                'type' => 'link',
                                'link' => [
                                    'url' => 'https://example.org',
                                    'title' => 'Ссылка',
                                ],
                            ],
                            [
                                'type' => 'video',
                                'video' => [
                                    'id' => 601,
                                    'owner_id' => -12345,
                                    'title' => 'Видео',
                                    'duration' => 15,
                                    'width' => 1280,
                                    'height' => 720,
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 9,
                        'owner_id' => -12345,
                        'from_id' => -12345,
                        'date' => 99,
                        'text' => 'Старый пост',
                    ],
                    [
                        'id' => 12,
                        'owner_id' => -99999,
                        'from_id' => -99999,
                        'date' => 103,
                        'text' => 'Чужой владелец',
                    ],
                ],
            ]);
        }

        throw new \RuntimeException(
            'Неожиданный VK API метод: ' . $url
        );
    }

    /**
     * @param mixed $response
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    private static function ok(mixed $response): array
    {
        $json = ['response' => $response];

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

$registered = ChannelAdapterRegistry::get('vk');
if (!$registered instanceof VkChannelAdapter) {
    fwrite(STDERR, "VK adapter не зарегистрирован runtime.\n");
    exit(1);
}

foreach ([
    ChannelCapability::PUBLISH_TEXT,
    ChannelCapability::PUBLISH_LINK,
    ChannelCapability::IMPORT_POSTS,
    ChannelCapability::POLLING,
] as $capability) {
    if (!in_array(
        $capability,
        $registered->capabilities(),
        true,
    )) {
        fwrite(
            STDERR,
            "VK adapter не объявил {$capability}.\n",
        );
        exit(1);
    }
}

$http = new VkSmokeHttpClient();
$adapter = new VkChannelAdapter($http);

$test = $adapter->testConnection(
    'parish_vk',
    'vk-user-token-smoke',
);
if (!$test->success) {
    fwrite(
        STDERR,
        "VK testConnection не прошёл: "
        . (string) $test->message
        . "\n",
    );
    exit(1);
}

$http->adminLevel = 1;
$limited = $adapter->testConnection(
    'parish_vk',
    'vk-user-token-smoke',
);
if ($limited->success) {
    fwrite(
        STDERR,
        "VK testConnection принял недостаточный admin_level.\n",
    );
    exit(1);
}
$http->adminLevel = 3;

$now = new \DateTimeImmutable('2026-09-30 00:00:00 UTC');
$connection = new SocialConnection(
    id: 2,
    publicId: 'vk-smoke-connection',
    provider: 'vk',
    name: 'VK smoke',
    targetRef: 'parish_vk',
    tokenEncrypted: '',
    settings: [],
    enabled: true,
    outboundEnabled: true,
    inboundEnabled: true,
    inboundPolicy: 'review',
    connectionKind: 'social',
    createdAt: $now,
    updatedAt: $now,
);

$item = new ChannelOutboundItem(
    sourceId: 'publication-vk-smoke',
    kind: 'news',
    title: 'VK smoke',
    text: 'VK smoke text',
    canonicalUrl:
        'https://church.example/publications/vk-smoke',
);

$firstPublish = $adapter->publish(
    $connection,
    'vk-user-token-smoke',
    $item,
);
$secondPublish = $adapter->publish(
    $connection,
    'vk-user-token-smoke',
    $item,
);

if (
    !$firstPublish->success
    || $firstPublish->remoteId !== '-12345:77'
    || $firstPublish->remoteUrl !== 'https://vk.com/wall-12345_77'
    || !$secondPublish->success
) {
    fwrite(
        STDERR,
        "VK publish сформировал неверный результат.\n",
    );
    exit(1);
}

$wallPostCalls = array_values(array_filter(
    $http->calls,
    static fn(array $call): bool =>
        str_ends_with(
            (string) ($call['url'] ?? ''),
            '/wall.post',
        ),
));

if (
    count($wallPostCalls) !== 2
    || ($wallPostCalls[0]['payload']['guid'] ?? null)
        !== ($wallPostCalls[1]['payload']['guid'] ?? null)
) {
    fwrite(
        STDERR,
        "VK retry не использует детерминированный guid.\n",
    );
    exit(1);
}

$batch = $adapter->pull(
    $connection,
    'vk-user-token-smoke',
    '100:1',
    50,
);

if (
    count($batch->items) !== 2
    || $batch->nextCursor !== '102:10'
) {
    fwrite(
        STDERR,
        "VK polling неверно обработал cursor или фильтр стены.\n",
    );
    exit(1);
}

$photo = $batch->items[0];
$video = $batch->items[1];

if (
    $photo->remoteId !== '-12345:11'
    || $photo->kind !== 'image'
    || $photo->text !== 'Новый пост'
    || $photo->canonicalUrl !== 'https://vk.com/wall-12345_11'
    || count($photo->media) !== 1
    || ($photo->media[0]['url'] ?? null)
        !== 'https://vk.example/x.jpg'
) {
    fwrite(
        STDERR,
        "VK photo post нормализован некорректно.\n",
    );
    exit(1);
}

if (
    $video->remoteId !== '-12345:10'
    || $video->kind !== 'video'
    || $video->updatedAt === null
    || count($video->media) !== 2
    || ($video->payload['edited'] ?? null) !== 102
) {
    fwrite(
        STDERR,
        "VK edited/video post нормализован некорректно.\n",
    );
    exit(1);
}

foreach ($http->calls as $call) {
    $url = (string) ($call['url'] ?? '');

    if (
        str_contains($url, 'access_token=')
        || str_contains($url, 'vk-user-token-smoke')
    ) {
        fwrite(
            STDERR,
            "VK access token попал в URL запроса.\n",
        );
        exit(1);
    }
}

echo "VK adapter smoke OK\n";
