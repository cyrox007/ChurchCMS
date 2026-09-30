<?php

declare(strict_types=1);

use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ChannelCapability;
use ChurchCMS\Modules\Social\ChannelHttpClient;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\SocialConnection;
use ChurchCMS\Modules\Social\TelegramChannelAdapter;
use DateTimeImmutable;
use RuntimeException;

require dirname(__DIR__) . '/core.php';

final class TelegramSmokeHttpClient implements ChannelHttpClient
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];

    public function getJson(
        string $url,
        array $query = [],
        array $headers = [],
    ): array {
        $this->calls[] = [
            'method' => 'GET',
            'url' => $url,
            'query' => $query,
        ];

        if (str_ends_with($url, '/getMe')) {
            return self::ok([
                'id' => 42,
                'is_bot' => true,
                'username' => 'churchcms_smoke_bot',
            ]);
        }

        if (str_ends_with($url, '/getChat')) {
            if (($query['chat_id'] ?? null) !== '@parish_channel') {
                throw new RuntimeException(
                    'getChat получил неверный chat_id.'
                );
            }

            return self::ok([
                'id' => -1001234567890,
                'type' => 'channel',
                'title' => 'Приход',
                'username' => 'parish_channel',
            ]);
        }

        if (str_ends_with($url, '/getUpdates')) {
            if (($query['offset'] ?? null) !== 100) {
                throw new RuntimeException(
                    'getUpdates не продолжил сохранённый cursor.'
                );
            }

            if (($query['limit'] ?? null) !== 50) {
                throw new RuntimeException(
                    'getUpdates получил неверный limit.'
                );
            }

            return self::ok([
                [
                    'update_id' => 100,
                    'channel_post' => [
                        'message_id' => 11,
                        'date' => 1790760000,
                        'chat' => [
                            'id' => -1001234567890,
                            'type' => 'channel',
                            'username' => 'parish_channel',
                            'title' => 'Приход',
                        ],
                        'text' => 'Первый текст',
                    ],
                ],
                [
                    'update_id' => 101,
                    'edited_channel_post' => [
                        'message_id' => 11,
                        'date' => 1790760000,
                        'edit_date' => 1790760060,
                        'chat' => [
                            'id' => -1001234567890,
                            'type' => 'channel',
                            'username' => 'parish_channel',
                            'title' => 'Приход',
                        ],
                        'caption' => 'Исправленный текст',
                        'video' => [
                            'file_id' => 'video-file-id',
                            'file_size' => 12345,
                            'duration' => 15,
                            'width' => 1280,
                            'height' => 720,
                            'mime_type' => 'video/mp4',
                        ],
                    ],
                ],
                [
                    'update_id' => 102,
                    'channel_post' => [
                        'message_id' => 99,
                        'date' => 1790760100,
                        'chat' => [
                            'id' => -1009999999999,
                            'type' => 'channel',
                            'username' => 'other_channel',
                        ],
                        'text' => 'Чужой канал',
                    ],
                ],
            ]);
        }

        throw new RuntimeException(
            'Неожиданный GET Telegram smoke: ' . $url
        );
    }

    public function postJson(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        $this->calls[] = [
            'method' => 'POST',
            'url' => $url,
            'payload' => $payload,
        ];

        if (!str_ends_with($url, '/sendMessage')) {
            throw new RuntimeException(
                'Неожиданный POST Telegram smoke: ' . $url
            );
        }

        if (($payload['chat_id'] ?? null) !== '@parish_channel') {
            throw new RuntimeException(
                'sendMessage получил неверный chat_id.'
            );
        }

        $text = (string) ($payload['text'] ?? '');
        if (
            $text === ''
            || !str_ends_with(
                $text,
                'https://church.example/publications/test'
            )
        ) {
            throw new RuntimeException(
                'sendMessage не получил canonical URL.'
            );
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($text, 'UTF-8')
            : strlen($text);

        if ($length > 4096) {
            throw new RuntimeException(
                'sendMessage превысил лимит Telegram.'
            );
        }

        return self::ok([
            'message_id' => 77,
            'chat' => [
                'id' => -1001234567890,
                'type' => 'channel',
                'username' => 'parish_channel',
            ],
            'date' => 1790760200,
            'text' => $text,
        ]);
    }

    public function postForm(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        throw new RuntimeException(
            'Telegram adapter не должен использовать postForm.'
        );
    }

    /**
     * @param mixed $result
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    private static function ok(mixed $result): array
    {
        $json = [
            'ok' => true,
            'result' => $result,
        ];

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

$registered = ChannelAdapterRegistry::get('telegram');
if (!$registered instanceof TelegramChannelAdapter) {
    fwrite(STDERR, "Telegram adapter не зарегистрирован runtime.\n");
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
            "Telegram adapter не объявил {$capability}.\n",
        );
        exit(1);
    }
}

$http = new TelegramSmokeHttpClient();
$adapter = new TelegramChannelAdapter($http);
$token = '123456:ABC_def-telegram-smoke';

$test = $adapter->testConnection(
    '@parish_channel',
    $token,
);
if (!$test->success) {
    fwrite(
        STDERR,
        "Telegram testConnection не прошёл: "
        . (string) $test->message
        . "\n",
    );
    exit(1);
}

$invalid = $adapter->testConnection(
    '',
    $token,
);
if ($invalid->success) {
    fwrite(
        STDERR,
        "Telegram testConnection принял пустой targetRef.\n",
    );
    exit(1);
}

$now = new DateTimeImmutable('2026-09-30 00:00:00 UTC');
$connection = new SocialConnection(
    id: 1,
    publicId: 'telegram-smoke-connection',
    provider: 'telegram',
    name: 'Telegram smoke',
    targetRef: '@parish_channel',
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

$publish = $adapter->publish(
    $connection,
    $token,
    new ChannelOutboundItem(
        sourceId: 'publication-smoke',
        kind: 'news',
        title: 'Новость',
        text: str_repeat('Текст ', 900),
        canonicalUrl:
            'https://church.example/publications/test',
    ),
);

if (
    !$publish->success
    || $publish->remoteId !== '77'
    || $publish->remoteUrl
        !== 'https://t.me/parish_channel/77'
) {
    fwrite(
        STDERR,
        "Telegram publish сформировал неверный результат.\n",
    );
    exit(1);
}

$batch = $adapter->pull(
    $connection,
    $token,
    '99',
    50,
);

if (
    $batch->nextCursor !== '102'
    || count($batch->items) !== 2
) {
    fwrite(
        STDERR,
        "Telegram polling неверно обработал cursor или фильтр target.\n",
    );
    exit(1);
}

$first = $batch->items[0];
$edited = $batch->items[1];

if (
    $first->remoteId !== '-1001234567890:11'
    || $first->kind !== 'post'
    || $first->text !== 'Первый текст'
    || $first->canonicalUrl
        !== 'https://t.me/parish_channel/11'
) {
    fwrite(
        STDERR,
        "Telegram channel_post нормализован некорректно.\n",
    );
    exit(1);
}

if (
    $edited->remoteId !== $first->remoteId
    || $edited->kind !== 'video'
    || $edited->text !== 'Исправленный текст'
    || $edited->updatedAt === null
    || count($edited->media) !== 1
    || ($edited->media[0]['file_id'] ?? null)
        !== 'video-file-id'
    || ($edited->payload['edited'] ?? null) !== true
) {
    fwrite(
        STDERR,
        "Telegram edited_channel_post нормализован некорректно.\n",
    );
    exit(1);
}

echo "Telegram adapter smoke OK\n";
