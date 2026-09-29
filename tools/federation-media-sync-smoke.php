<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\FederationMediaSyncWorker;
use ChurchCMS\Modules\Organizations\FederationRemoteProjectionRepository;
use ChurchCMS\Modules\Organizations\FederationRepository;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\FederationSyncTransport;
use ChurchCMS\Modules\Organizations\FederationWorkerSyncStateRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

final class ScriptedMediaFederationTransport implements FederationSyncTransport
{
    /** @var list<array{path:string,result?:array<string,mixed>,error?:string}> */
    private array $steps;

    /** @var list<array{path:string,query:array<string,string|int>,token:string}> */
    public array $calls = [];

    /**
     * @param list<array{path:string,result?:array<string,mixed>,error?:string}> $steps
     */
    public function __construct(array $steps)
    {
        $this->steps = $steps;
    }

    public function getJson(
        string $baseUrl,
        string $path,
        array $query,
        string $token,
    ): array {
        $this->calls[] = [
            'path' => $path,
            'query' => $query,
            'token' => $token,
        ];

        $step = array_shift($this->steps);
        if (!is_array($step) || ($step['path'] ?? null) !== $path) {
            throw new RuntimeException(
                'Тестовый Media transport получил неожиданный запрос.'
            );
        }

        if (isset($step['error'])) {
            throw new RuntimeException((string) $step['error']);
        }

        $result = $step['result'] ?? null;
        if (!is_array($result)) {
            throw new RuntimeException(
                'Тестовый Media transport не содержит ответ.'
            );
        }

        return $result;
    }
}

function mediaSyncPage(
    array $items,
    ?string $nextUpdatedSince,
    ?string $nextAfter,
    bool $hasMore = false,
): array {
    return [
        'data' => $items,
        'meta' => [
            'sync' => [
                'next_updated_since' => $nextUpdatedSince,
                'next_after' => $nextAfter,
                'has_more' => $hasMore,
            ],
        ],
    ];
}

$pdo = DatabaseManager::getInstance()->connection();
$siteKey = 'media-federation-worker-smoke';

$root = OrganizationService::fromDatabase()->ensureSiteRoot(
    'Тестовый принимающий узел медиатеки',
    'diocese',
    $siteKey,
);

$remoteInstanceId = 'b1000000-0000-4000-8000-000000000001';
$remoteOrganizationId = 'b2000000-0000-4000-8000-000000000002';
$remoteOwnerId = 'b3000000-0000-4000-8000-000000000003';
$remoteMediaId = 'b4000000-0000-4000-8000-000000000004';
$token = 'media-federation-worker-token';

$federation = FederationService::fromDatabase();
$linkPublicId = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteBaseUrl: 'https://media.example',
    inboundScopes: ['content.read'],
    outboundScopes: [],
    outboundToken: $token,
    remoteProfile: 'parish',
    remoteName: 'Удалённый приход Media',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $linkPublicId,
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteProfile: 'parish',
    remoteName: 'Удалённый приход Media',
    siteKey: $siteKey,
);

$link = FederationRepository::fromDatabase()->findByPublicId(
    $linkPublicId,
    $siteKey,
);
if ($link === null) {
    fwrite(STDERR, "Federation link Media не создан.\n");
    exit(1);
}

$states = FederationWorkerSyncStateRepository::fromDatabase();
$documentsCursor = json_encode(
    [
        'version' => 1,
        'documents' => [
            'updated_since' => '2040-01-01T00:00:00+00:00',
            'after' => null,
        ],
        'tombstones' => [
            'updated_since' => '2040-01-01T00:00:00+00:00',
            'after' => null,
        ],
    ],
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
);
$states->saveCursor(
    $link->id,
    'documents',
    null,
    $documentsCursor,
);

$updatedAt = '2043-02-01T10:00:00+00:00';
$deletedAt = '2043-02-01T10:05:00+00:00';

$transport = new ScriptedMediaFederationTransport([
    [
        'path' => '/api/v1/partner/media',
        'result' => mediaSyncPage(
            [[
                'id' => $remoteMediaId,
                'type' => 'media',
                'media_type' => 'image',
                'mime_type' => 'image/jpeg',
                'bytes' => 12345,
                'sha256' => hash('sha256', 'remote-media'),
                'title' => 'Удалённый собор',
                'alt_text' => 'Фасад собора',
                'organization_owner_id' => $remoteOwnerId,
                'blob_available' => false,
                'updated_at' => $updatedAt,
                'url' => null,
            ]],
            $updatedAt,
            $remoteMediaId,
        ),
    ],
    [
        'path' => '/api/v1/partner/media/tombstones',
        'error' => 'Тестовый сбой tombstone-потока Media.',
    ],
    [
        'path' => '/api/v1/partner/media',
        'result' => mediaSyncPage([], null, null),
    ],
    [
        'path' => '/api/v1/partner/media/tombstones',
        'result' => mediaSyncPage(
            [[
                'id' => $remoteMediaId,
                'type' => 'media',
                'action' => 'delete',
                'reason' => 'archived',
                'organization_owner_id' => $remoteOwnerId,
                'updated_at' => $deletedAt,
            ]],
            $deletedAt,
            $remoteMediaId,
        ),
    ],
]);

$worker = new FederationMediaSyncWorker(
    $pdo,
    $transport,
);

$first = $worker->run(
    siteKey: $siteKey,
    pageSize: 1,
);

$mediaState = $states->state($link->id, 'media');
$documentsState = $states->state(
    $link->id,
    'documents',
);

if (
    $first['failed'] !== 1
    || $first['succeeded'] !== 0
    || $mediaState === null
    || $mediaState['cursor'] === null
    || $mediaState['last_sync_error'] === null
    || ($documentsState['cursor'] ?? null) !== $documentsCursor
) {
    fwrite(
        STDERR,
        "Media worker повредил независимое состояние после сбоя.\n",
    );
    exit(1);
}

$partial = json_decode(
    $mediaState['cursor'],
    true,
    32,
    JSON_THROW_ON_ERROR,
);
if (
    ($partial['media']['after'] ?? null) !== $remoteMediaId
    || ($partial['tombstones']['after'] ?? null) !== null
) {
    fwrite(
        STDERR,
        "Media worker неверно сохранил частичный курсор.\n",
    );
    exit(1);
}

$projection = FederationRemoteProjectionRepository::fromDatabase()
    ->find(
        $link->id,
        'media',
        $remoteMediaId,
    );

if (
    $projection === null
    || $projection->isDeleted()
    || str_contains(
        json_encode(
            $projection->payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ),
        'original_name',
    )
) {
    fwrite(
        STDERR,
        "Media worker не сохранил безопасную remote projection.\n",
    );
    exit(1);
}

$second = $worker->run(
    siteKey: $siteKey,
    pageSize: 1,
);

$mediaState = $states->state($link->id, 'media');
$projection = FederationRemoteProjectionRepository::fromDatabase()
    ->find(
        $link->id,
        'media',
        $remoteMediaId,
    );

if (
    $second['succeeded'] !== 1
    || $second['failed'] !== 0
    || $second['tombstones'] !== 1
    || $mediaState === null
    || $mediaState['last_sync_error'] !== null
    || $mediaState['last_sync_at'] === null
    || $projection === null
    || !$projection->isDeleted()
) {
    fwrite(
        STDERR,
        "Повторный Media sync не завершил поток корректно.\n",
    );
    exit(1);
}

if (
    count($transport->calls) !== 4
    || ($transport->calls[0]['query']['updated_since'] ?? null)
        !== '1970-01-01T00:00:00+00:00'
    || isset($transport->calls[0]['query']['after'])
    || ($transport->calls[2]['query']['after'] ?? null)
        !== $remoteMediaId
    || isset($transport->calls[3]['query']['after'])
    || $transport->calls[0]['token'] !== $token
) {
    fwrite(
        STDERR,
        "Media worker неверно продолжил составные курсоры.\n",
    );
    exit(1);
}

echo "Federation sync Media и независимое состояние worker проверены\n";
