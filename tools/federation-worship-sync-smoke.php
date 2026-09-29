<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\FederationWorshipSyncWorker;
use ChurchCMS\Modules\Organizations\FederationRemoteProjectionRepository;
use ChurchCMS\Modules\Organizations\FederationRepository;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\FederationSyncTransport;
use ChurchCMS\Modules\Organizations\FederationWorkerSyncStateRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

final class ScriptedWorshipFederationTransport implements FederationSyncTransport
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
                'Тестовый Worship transport получил неожиданный запрос.'
            );
        }

        if (isset($step['error'])) {
            throw new RuntimeException((string) $step['error']);
        }

        $result = $step['result'] ?? null;
        if (!is_array($result)) {
            throw new RuntimeException(
                'Тестовый Worship transport не содержит ответ.'
            );
        }

        return $result;
    }
}

function worshipSyncPage(
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
$siteKey = 'worship-federation-worker-smoke';

$root = OrganizationService::fromDatabase()->ensureSiteRoot(
    'Тестовый принимающий узел богослужений',
    'diocese',
    $siteKey,
);

$remoteInstanceId = 'a1000000-0000-4000-8000-000000000001';
$remoteOrganizationId = 'a2000000-0000-4000-8000-000000000002';
$remoteOwnerId = 'a3000000-0000-4000-8000-000000000003';
$remoteWorshipId = 'a4000000-0000-4000-8000-000000000004';
$token = 'worship-federation-worker-token';

$federation = FederationService::fromDatabase();
$linkPublicId = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteBaseUrl: 'https://worship.example',
    inboundScopes: ['content.read'],
    outboundScopes: [],
    outboundToken: $token,
    remoteProfile: 'parish',
    remoteName: 'Удалённый приход богослужений',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $linkPublicId,
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteProfile: 'parish',
    remoteName: 'Удалённый приход богослужений',
    siteKey: $siteKey,
);

$link = FederationRepository::fromDatabase()->findByPublicId(
    $linkPublicId,
    $siteKey,
);
if ($link === null) {
    fwrite(STDERR, "Federation link богослужений не создан.\n");
    exit(1);
}

$states = FederationWorkerSyncStateRepository::fromDatabase();
$publicationCursor = json_encode(
    [
        'version' => 1,
        'publications' => [
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
    'publications',
    null,
    $publicationCursor,
);

$updatedAt = '2042-02-01T10:00:00+00:00';
$deletedAt = '2042-02-01T10:05:00+00:00';

$transport = new ScriptedWorshipFederationTransport([
    [
        'path' => '/api/v1/partner/worship',
        'result' => worshipSyncPage(
            [[
                'id' => $remoteWorshipId,
                'type' => 'worship',
                'status' => 'cancelled',
                'title' => 'Божественная литургия',
                'service_type' => 'divine_liturgy',
                'organization_owner_id' => $remoteOwnerId,
                'starts_at' => '2042-03-01T08:00:00+00:00',
                'ends_at' => null,
                'location' => 'Главный храм',
                'updated_at' => $updatedAt,
                'url' => null,
            ]],
            $updatedAt,
            $remoteWorshipId,
        ),
    ],
    [
        'path' => '/api/v1/partner/worship/tombstones',
        'error' => 'Тестовый сбой tombstone-потока Worship.',
    ],
    [
        'path' => '/api/v1/partner/worship',
        'result' => worshipSyncPage([], null, null),
    ],
    [
        'path' => '/api/v1/partner/worship/tombstones',
        'result' => worshipSyncPage(
            [[
                'id' => $remoteWorshipId,
                'type' => 'worship',
                'action' => 'delete',
                'reason' => 'withdrawn',
                'organization_owner_id' => $remoteOwnerId,
                'updated_at' => $deletedAt,
            ]],
            $deletedAt,
            $remoteWorshipId,
        ),
    ],
]);

$worker = new FederationWorshipSyncWorker(
    $pdo,
    $transport,
);

$first = $worker->run(
    siteKey: $siteKey,
    pageSize: 1,
);

$worshipState = $states->state($link->id, 'worship');
$publicationState = $states->state(
    $link->id,
    'publications',
);

if (
    $first['failed'] !== 1
    || $first['succeeded'] !== 0
    || $worshipState === null
    || $worshipState['cursor'] === null
    || $worshipState['last_sync_error'] === null
    || ($publicationState['cursor'] ?? null) !== $publicationCursor
) {
    fwrite(
        STDERR,
        "Worship worker повредил независимое состояние worker после сбоя.\n",
    );
    exit(1);
}

$partial = json_decode(
    $worshipState['cursor'],
    true,
    32,
    JSON_THROW_ON_ERROR,
);
if (
    ($partial['worship']['after'] ?? null) !== $remoteWorshipId
    || ($partial['tombstones']['after'] ?? null) !== null
) {
    fwrite(
        STDERR,
        "Worship worker неверно сохранил частичный курсор.\n",
    );
    exit(1);
}

$second = $worker->run(
    siteKey: $siteKey,
    pageSize: 1,
);

$worshipState = $states->state($link->id, 'worship');
$publicationState = $states->state(
    $link->id,
    'publications',
);
$projection = FederationRemoteProjectionRepository::fromDatabase()
    ->find(
        $link->id,
        'worship',
        $remoteWorshipId,
    );

if (
    $second['succeeded'] !== 1
    || $second['failed'] !== 0
    || $second['tombstones'] !== 1
    || $worshipState === null
    || $worshipState['last_sync_error'] !== null
    || $worshipState['last_sync_at'] === null
    || ($publicationState['cursor'] ?? null) !== $publicationCursor
    || $projection === null
    || !$projection->isDeleted()
) {
    fwrite(
        STDERR,
        "Повторный Worship sync не завершил независимый поток корректно.\n",
    );
    exit(1);
}

if (
    count($transport->calls) !== 4
    || ($transport->calls[0]['query']['updated_since'] ?? null)
        !== '1970-01-01T00:00:00+00:00'
    || isset($transport->calls[0]['query']['after'])
    || ($transport->calls[2]['query']['after'] ?? null)
        !== $remoteWorshipId
    || isset($transport->calls[3]['query']['after'])
    || $transport->calls[0]['token'] !== $token
) {
    fwrite(
        STDERR,
        "Worship worker неверно продолжил составные курсоры.\n",
    );
    exit(1);
}

echo "Federation sync богослужений и независимое состояние worker проверены\n";
