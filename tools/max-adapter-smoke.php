<?php

declare(strict_types=1);

use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ChannelCapability;
use ChurchCMS\Modules\Social\ChannelHttpClient;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\MaxChannelAdapter;
use ChurchCMS\Modules\Social\SocialConnection;

require dirname(__DIR__) . '/core.php';

final class MaxSmokeHttpClient implements ChannelHttpClient
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];

    public string $chatStatus = 'active';

    public function getJson(
        string $url,
        array $query = [],
        array $headers = [],
    ): array {
        $this->assertAuth($url, $headers);
        $this->calls[] = [
            'method' => 'GET',
            'url' => $url,
            'query' => $query,
            'headers' => $headers,
        ];

        if ($url === 'https://platform-api2.max.ru/me') {
            return self::ok([
                'user_id' => 501,
                'name' => 'ChurchCMS smoke bot',
                'username' => 'churchcms_smoke',
            ]);
        }

        if ($url === 'https://platform-api2.max.ru/chats/777') {
            return self::ok([
                'chat_id' => 777,
                'type' => 'channel',
                'status' => $this->chatStatus,
                'title' => 'Приход',
                'last_event_time' => 1790760000000,
                'participants_count' => 12,
                'icon' => null,
                'is_public' => true,
                'description' => 'Тестовый канал',
            ]);
        }

        if ($url === 'https://platform-api2.max.ru/updates') {
            if (
                ($query['marker'] ?? null) !== 101
                || ($query['limit'] ?? null) !== 50
                || ($query['timeout'] ?? null) !== 0
                || ($query['types'] ?? null)
                    !== 'message_created,message_edited'
            ) {
                throw new \RuntimeException(
                    'MAX /updates получил неверные параметры.'
                );
            }

            return self::ok([
                'updates' => [
                    [
                        'update_type' => 'message_created',
                        'timestamp' => 1790760000100,
                        'message' => [
                            'recipient' => [
                                'chat_id' => 777,
                                'chat_type' => 'channel',
                                'user_id' => null,
                                'post_id' => null,
                            ],
                            'timestamp' => 1790760000000,
                            'url' => 'https://max.ru/channel/post-1',
                            'body' => [
                                'mid' => 'mid-1',
                                'seq' => 10,
                                'text' => 'Первый MAX пост',
                                'attachments' => [[
                                    'type' => 'image',
                                    'payload' => [
                                        'photo_id' => 9001,
                                        'token' => 'image-token',
                                    ],
                                ]],
                                'link' => null,
                            ],
                        ],
                    ],
                    [
                        'update_type' => 'message_edited',
                        'timestamp' => 1790760060000,
                        'message' => [
                            'recipient' => [
                                'chat_id' => 777,
                                'chat_type' => 'channel',
                                'user_id' => null,
                                'post_id' => null,
                            ],
                            'timestamp' => 1790760000000,
                            'url' => 'https://max.ru/channel/post-1',
                            'body' => [
                                'mid' => 'mid-1',
                                'seq' => 10,
                                'text' => 'Исправленный MAX пост',
                                'attachments' => [[
                                    'type' => 'file',
                                    'filename' => 'document.pdf',
                                    'size' => 12345,
                                    'payload' => [
                                        'file_id' => 7001,
                                        'token' => 'file-token',
                                    ],
                                ]],
                                'link' => null,
                            ],
                        ],
                    ],
                    [
                        'update_type' => 'message_created',
                        'timestamp' => 1790760070000,
                        'message' => [
                            'recipient' => [
                                'chat_id' => 999,
                                'chat_type' => 'channel',
                                'user_id' => null,
                                'post_id' => null,
                            ],
                            'timestamp' => 1790760070000,
                            'url' => 'https://max.ru/other/post',
                            'body' => [
                                'mid' => 'foreign',
                                'seq' => 11,
                                'text' => 'Чужой канал',
                                'attachments' => [],
                                'link' => null,
                            ],
                        ],
                    ],
                ],
                'marker' => 205,
            ]);
        }

        throw new \RuntimeException(
            'Неожиданный GET MAX smoke: ' . $url
        );
    }

    public function postJson(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        $this->assertAuth($url, $headers);
        $this->calls[] = [
            'method' => 'POST',
            'url' => $url,
            'payload' => $payload,
            'headers' => $headers,
        ];

        if (
            $url !== 'https://platform-api2.max.ru/messages?chat_id=777'
        ) {
            throw new \RuntimeException(
                'MAX sendMessage получил неверный URL.'
            );
        }

        if (
            ($payload['attachments'] ?? null) !== []
            || array_key_exists('link', $payload) === false
            || $payload['link'] !== null
            || ($payload['notify'] ?? null) !== true
        ) {
            throw new \RuntimeException(
                'MAX sendMessage нарушил NewMessageBody.'
            );
        }

        $text = (string) ($payload['text'] ?? '');
        $length = function_exists('mb_strlen')
            ? mb_strlen($text, 'UTF-8')
            : strlen($text);

        if (
            $length > 4000
            || !str_ends_with(
                $text,
                'https://church.example/publications/max-smoke',
            )
        ) {
            throw new \RuntimeException(
                'MAX sendMessage нарушил лимит текста или canonical URL.'
            );
        }

        return self::ok([
            'message' => [
                'recipient' => [
                    'chat_id' => 777,
                    'chat_type' => 'channel',
                    'user_id' => null,
                    'post_id' => null,
                ],
                'timestamp' => 1790760100000,
                'url' => 'https://max.ru/channel/sent-post',
                'body' => [
                    'mid' => 'sent-mid',
                    'seq' => 12,
                    'text' => $text,
                    'attachments' => [],
                    'link' => null,
                ],
            ],
        ]);
    }

    public function postForm(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        throw new \RuntimeException(
            'MAX adapter не должен использовать form POST.'
        );
    }

    private function assertAuth(
        string $url,
        array $headers,
    ): void {
        if (
            ($headers['Authorization'] ?? null)
                !== 'max-access-token-smoke'
        ) {
            throw new \RuntimeException(
                'MAX access token не передан в Authorization.'
            );
        }

        if (str_contains($url, 'max-access-token-smoke')) {
            throw new \RuntimeException(
                'MAX access token попал в URL.'
            );
        }
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

$registered = ChannelAdapterRegistry::get('max');
if (!$registered instanceof MaxChannelAdapter) {
    fwrite(STDERR, "MAX adapter не зарегистрирован runtime.\n");
    exit(1);
}

foreach ([
    ChannelCapability::PUBLISH_TEXT,
    ChannelCapability::PUBLISH_LINK,
    ChannelCapability::IMPORT_POSTS,
    ChannelCapability::WEBHOOK,
] as $capability) {
    if (!in_array(
        $capability,
        $registered->capabilities(),
        true,
    )) {
        fwrite(
            STDERR,
            "MAX adapter не объявил {$capability}.\n",
        );
        exit(1);
    }
} 

if (in_array(
    ChannelCapability::POLLING,
    $registered->capabilities(),
    true,
)) {
    fwrite(
        STDERR,
        "Production MAX adapter не должен объявлять polling при активном webhook.\n",
    );
    exit(1);
}

$http = new MaxSmokeHttpClient();
$adapter = new MaxChannelAdapter($http);

$test = $adapter->testConnection(
    '777',
    'max-access-token-smoke',
);
if (!$test->success) {
    fwrite(
        STDERR,
        "MAX testConnection не прошёл: "
        . (string) $test->message
        . "\n",
    );
    exit(1);
}

$http->chatStatus = 'removed';
$removed = $adapter->testConnection(
    '777',
    'max-access-token-smoke',
);
if ($removed->success) {
    fwrite(
        STDERR,
        "MAX testConnection принял удалённого бота.\n",
    );
    exit(1);
}
$http->chatStatus = 'active';

$now = new \DateTimeImmutable('2026-09-30 00:00:00 UTC');
$connection = new SocialConnection(
    id: 3,
    publicId: 'max-smoke-connection',
    provider: 'max',
    name: 'MAX smoke',
    targetRef: '777',
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

$published = $adapter->publish(
    $connection,
    'max-access-token-smoke',
    new ChannelOutboundItem(
        sourceId: 'publication-max-smoke',
        kind: 'news',
        title: 'MAX smoke',
        text: str_repeat('Текст ', 800),
        canonicalUrl:
            'https://church.example/publications/max-smoke',
    ),
);

if (
    !$published->success
    || $published->remoteId !== '777:sent-mid'
    || $published->remoteUrl
        !== 'https://max.ru/channel/sent-post'
) {
    fwrite(
        STDERR,
        "MAX publish сформировал неверный результат.\n",
    );
    exit(1);
}

$batch = $adapter->pull(
    $connection,
    'max-access-token-smoke',
    '101',
    50,
);

if (
    count($batch->items) !== 2
    || $batch->nextCursor !== '205'
) {
    fwrite(
        STDERR,
        "MAX polling неверно обработал marker или target chat.\n",
    );
    exit(1);
}

$created = $batch->items[0];
$edited = $batch->items[1];

if (
    $created->remoteId !== '777:mid-1'
    || $created->kind !== 'image'
    || $created->text !== 'Первый MAX пост'
    || $created->canonicalUrl
        !== 'https://max.ru/channel/post-1'
    || count($created->media) !== 1
    || ($created->media[0]['photo_id'] ?? null) !== 9001
) {
    fwrite(
        STDERR,
        "MAX message_created нормализован некорректно.\n",
    );
    exit(1);
}

if (
    $edited->remoteId !== $created->remoteId
    || $edited->kind !== 'document'
    || $edited->text !== 'Исправленный MAX пост'
    || $edited->updatedAt === null
    || count($edited->media) !== 1
    || ($edited->media[0]['filename'] ?? null)
        !== 'document.pdf'
    || ($edited->payload['update_type'] ?? null)
        !== 'message_edited'
) {
    fwrite(
        STDERR,
        "MAX message_edited нормализован некорректно.\n",
    );
    exit(1);
}

echo "MAX adapter smoke OK\n";
