<?php

declare(strict_types=1);

use ChurchCMS\Core\SecretVault;
use ChurchCMS\Modules\Social\ChannelAdapter;
use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ChannelCapability;
use ChurchCMS\Modules\Social\ChannelConnectionTester;
use ChurchCMS\Modules\Social\ChannelCredentialsOptional;
use ChurchCMS\Modules\Social\ChannelHttpClient;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\ChannelPublishResult;
use ChurchCMS\Modules\Social\ChannelPullBatch;
use ChurchCMS\Modules\Social\ChannelConnectionTestResult;
use ChurchCMS\Modules\Social\RutubeChannelAdapter;
use ChurchCMS\Modules\Social\SocialConnection;
use ChurchCMS\Modules\Social\SocialConnectionService;

require dirname(__DIR__) . '/core.php';

final class RutubeSmokeHttpClient implements ChannelHttpClient
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];

    public function getJson(
        string $url,
        array $query = [],
        array $headers = [],
    ): array {
        $this->calls[] = [
            'url' => $url,
            'query' => $query,
        ];

        if (
            $url
            !== 'https://rutube.ru/api/video/person/28267015/'
        ) {
            throw new \RuntimeException(
                'Rutube adapter обратился к неверному URL.'
            );
        }

        $page = (int) ($query['page'] ?? 0);
        if (($query['format'] ?? null) !== 'json') {
            throw new \RuntimeException(
                'Rutube adapter не запросил JSON.'
            );
        }

        if ($page === 1) {
            return self::ok([
                'has_next' => true,
                'results' => [
                    self::item(
                        'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                        1790748000,
                        'Новое видео 2',
                        'Описание 2',
                    ),
                ],
            ]);
        }

        if ($page === 2) {
            return self::ok([
                'has_next' => false,
                'results' => [
                    self::item(
                        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                        1790744400,
                        'Новое видео 1',
                        'Описание 1',
                    ),
                    self::item(
                        '11111111111111111111111111111111',
                        1790740800,
                        'Старое видео',
                        'Старое описание',
                    ),
                ],
            ]);
        }

        throw new \RuntimeException(
            'Rutube adapter запросил лишнюю страницу.'
        );
    }

    public function postJson(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        throw new \RuntimeException(
            'Rutube public adapter не должен использовать POST.'
        );
    }

    public function postForm(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        throw new \RuntimeException(
            'Rutube public adapter не должен использовать form POST.'
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function item(
        string $id,
        int $publishedAt,
        string $title,
        string $description,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'duration' => 321,
            'thumbnail_url' =>
                'https://pic.rtbcdn.ru/video/test/'
                . $id
                . '.jpg',
            'video_url' =>
                'https://rutube.ru/video/'
                . $id
                . '/',
            'publication_ts' => $publishedAt,
            'author' => [
                'id' => 28267015,
                'name' => 'Тестовый канал',
            ],
            'hits' => 42,
            'is_paid' => false,
        ];
    }

    /**
     * @param array<string,mixed> $json
     * @return array{
     *     status:int,
     *     body:string,
     *     json:array<string,mixed>|null
     * }
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

$registered = ChannelAdapterRegistry::get('rutube');
if (!$registered instanceof RutubeChannelAdapter) {
    fwrite(STDERR, "Rutube adapter не зарегистрирован runtime.\n");
    exit(1);
}

$available = SocialConnectionService::fromDatabase()
    ->availableAdapters();
$rutubeInfo = null;

foreach ($available as $adapterInfo) {
    if (($adapterInfo['id'] ?? null) === 'rutube') {
        $rutubeInfo = $adapterInfo;
        break;
    }
}

if (
    !is_array($rutubeInfo)
    || ($rutubeInfo['credentials_required'] ?? true) !== false
    || ($rutubeInfo['can_import'] ?? false) !== true
    || ($rutubeInfo['can_publish'] ?? true) !== false
) {
    fwrite(
        STDERR,
        "Мастер неверно описывает capabilities Rutube.\n",
    );
    exit(1);
}

$publicAdapter = new class implements
    ChannelAdapter,
    ChannelConnectionTester,
    ChannelCredentialsOptional
{
    public function providerId(): string
    {
        return 'public-smoke';
    }

    public function label(): string
    {
        return 'Public smoke';
    }

    public function capabilities(): array
    {
        return [
            ChannelCapability::IMPORT_POSTS,
            ChannelCapability::POLLING,
        ];
    }

    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult {
        return new ChannelConnectionTestResult(
            $targetRef === 'public-target'
            && $credentials === '',
        );
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        return new ChannelPublishResult(
            false,
            error: 'Inbound only.',
        );
    }

    public function pull(
        SocialConnection $connection,
        string $credentials,
        ?string $cursor,
        int $limit = 50,
    ): ChannelPullBatch {
        return new ChannelPullBatch([]);
    }
};

ChannelAdapterRegistry::register($publicAdapter);

$service = SocialConnectionService::fromDatabase();
$created = $service->create(
    provider: 'public-smoke',
    name: 'Публичный адаптер',
    targetRef: 'public-target',
    credentials: '',
    outboundEnabled: false,
    inboundEnabled: true,
);

if (
    !$created->enabled
    || !$created->inboundEnabled
    || $created->outboundEnabled
    || SecretVault::decrypt($created->tokenEncrypted)
        !== 'churchcms:public-adapter'
) {
    fwrite(
        STDERR,
        "Credentialless подключение сохранено некорректно.\n",
    );
    exit(1);
}

try {
    $service->create(
        provider: 'public-smoke',
        name: 'Публичный адаптер с лишним секретом',
        targetRef: 'public-target',
        credentials: 'лишний-секрет',
        outboundEnabled: false,
        inboundEnabled: true,
    );
    fwrite(
        STDERR,
        "Credentialless adapter ошибочно принял секрет.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

$http = new RutubeSmokeHttpClient();
$adapter = new RutubeChannelAdapter($http);

$test = $adapter->testConnection(
    '28267015',
    '',
);
if (!$test->success) {
    fwrite(
        STDERR,
        "Rutube testConnection не прошёл: "
        . (string) $test->message
        . "\n",
    );
    exit(1);
}

$invalid = $adapter->testConnection(
    '28267015',
    'secret',
);
if ($invalid->success) {
    fwrite(
        STDERR,
        "Rutube testConnection ошибочно принял секрет.\n",
    );
    exit(1);
}

$now = new \DateTimeImmutable(
    '2026-09-30 00:00:00 UTC',
);
$connection = new SocialConnection(
    id: 5,
    publicId: 'rutube-smoke-connection',
    provider: 'rutube',
    name: 'Rutube smoke',
    targetRef: '28267015',
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

$cursor = '1790740800|11111111111111111111111111111111';

$batch = $adapter->pull(
    $connection,
    'churchcms:public-adapter',
    $cursor,
    2,
);

if (
    count($batch->items) !== 2
    || $batch->items[0]->remoteId
        !== 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
    || $batch->items[1]->remoteId
        !== 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
    || $batch->nextCursor
        !== '1790748000|bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
) {
    fwrite(
        STDERR,
        "Rutube polling неверно обработал пагинацию или cursor.\n",
    );
    exit(1);
}

$first = $batch->items[0];

if (
    $first->kind !== 'video'
    || $first->title !== 'Новое видео 1'
    || $first->text !== 'Описание 1'
    || $first->canonicalUrl
        !== 'https://rutube.ru/video/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/'
    || count($first->media) !== 1
    || ($first->media[0]['duration'] ?? null) !== 321
    || ($first->payload['author_id'] ?? null) !== 28267015
) {
    fwrite(
        STDERR,
        "Rutube video нормализовано некорректно.\n",
    );
    exit(1);
}

$publish = $adapter->publish(
    $connection,
    '',
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
        "Inbound-only Rutube adapter ошибочно подтвердил outbound.\n",
    );
    exit(1);
}

echo "Rutube inbound adapter smoke OK\n";
