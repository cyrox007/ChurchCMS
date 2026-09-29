<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\FederationEventSyncWorker;
use ChurchCMS\Modules\Organizations\FederationRemoteProjectionRepository;
use ChurchCMS\Modules\Organizations\FederationRepository;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\FederationSyncTransport;
use ChurchCMS\Modules\Organizations\FederationWorkerSyncStateRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

final class ScriptedEventFederationTransport implements FederationSyncTransport
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
                'Тестовый Events transport получил неожиданный запрос.'
            );
        }

        if (isset($step['error'])) {
            throw new RuntimeException((string) $step['error']);
        }

        $result = $step['result'] ?? null;
        if (!is_array($result)) {
            throw new RuntimeException(
                'Тестовый Events transport не содержит ответ.'
            );
        }

        return $result;
    }
}

function eventSyncPage(
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
$siteKey = 'event-federation-worker-smoke';

$root = OrganizationService::fromDatabase()->ensureSiteRoot(
    'Тестовый принимающий узел событий',
    'diocese',
    $siteKey,
);

$remoteInstanceId = 'a1000000-0000-4000-8000-000000000001';
$remoteOrganizationId = 'a2000000-0000-4000-8000-000000000002';
$remoteOwnerId = 'a3000000-0000-4000-8000-000000000003';
$remoteEventId = 'a4000000-0000-4000-8000-000000000004';
$token = 'event-federation-worker-token';

$federation = FederationService::fromDatabase();
$linkPublicId = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteBaseUrl: 'https://events.example',
    inboundScopes: ['content.read'],
    outboundScopes: [],
    outboundToken: $token,
    remoteProfile: 'parish',
    remoteName: 'Удалённый приход событий',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $linkPublicId,
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteProfile: 'parish',
    remoteName: 'Удалённый приход событий',
    siteKey: $siteKey,
);

$link = FederationRepository::fromDatabase()->findByPublicId(
    $linkPublicId,
    $siteKey,
);
if ($link === null) {
    fwrite(STDERR, "Federation link событий не создан.\n");
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

$transport = new ScriptedEventFederationTransport([
    [
        'path' => '/api/v1/partner/events',
        'result' => eventSyncPage(
            [[
                'id' => $remoteEventId,
                'type' => 'event',
                'title' => 'Удалённое событие',
                'excerpt' => 'Краткое описание события.',
                'organization_owner_id' => $remoteOwnerId,
                'starts_at' => '2042-03-01T08:00:00+00:00',
                'ends_at' => null,
                'all_day' => false,
                'location' => 'Приходской дом',
                'updated_at' => $updatedAt,
                'url' => null,
            ]],
            $updatedAt,
            $remoteEventId,
        ),
    ],
    [
        'path' => '/api/v1/partner/events/tombstones',
        'error' => 'Тестовый сбой tombstone-потока Events.',
    ],
    [
        'path' => '/api/v1/partner/events',
        'result' => eventSyncPage([], null, null),
    ],
    [
        'path' => '/api/v1/partner/events/tombstones',
        'result' => eventSyncPage(
            [[
                'id' => $remoteEventId,
                'type' => 'event',
                'action' => 'delete',
                'reason' => 'cancelled',
                'organization_owner_id' => $remoteOwnerId,
                'updated_at' => $deletedAt,
            ]],
            $deletedAt,
            $remoteEventId,
        ),
    ],
]);

$worker = new FederationEventSyncWorker(
    $pdo,
    $transport,
);

$first = $worker->run(
    siteKey: $siteKey,
    pageSize: 1,
);

$eventState = $states->state($link->id, 'events');
$publicationState = $states->state(
    $link->id,
    'publications',
);

if (
    $first['failed'] !== 1
    || $first['succeeded'] !== 0
    || $eventState === null
    || $eventState['cursor'] === null
    || $eventState['last_sync_error'] === null
    || ($publicationState['cursor'] ?? null) !== $publicationCursor
) {
    fwrite(
        STDERR,
        "Events worker повредил независимое состояние worker после сбоя.\n",
    );
    exit(1);
}

$partial = json_decode(
    $eventState['cursor'],
    true,
    32,
    JSON_THROW_ON_ERROR,
);
if (
    ($partial['events']['after'] ?? null) !== $remoteEventId
    || ($partial['tombstones']['after'] ?? null) !== null
) {
    fwrite(
        STDERR,
        "Events worker неверно сохранил частичный курсор.\n",
    );
    exit(1);
}

$second = $worker->run(
    siteKey: $siteKey,
    pageSize: 1,
);

$eventState = $states->state($link->id, 'events');
$publicationState = $states->state(
    $link->id,
    'publications',
);
$projection = FederationRemoteProjectionRepository::fromDatabase()
    ->find(
        $link->id,
        'event',
        $remoteEventId,
    );

if (
    $second['succeeded'] !== 1
    || $second['failed'] !== 0
    || $second['tombstones'] !== 1
    || $eventState === null
    || $eventState['last_sync_error'] !== null
    || $eventState['last_sync_at'] === null
    || ($publicationState['cursor'] ?? null) !== $publicationCursor
    || $projection === null
    || !$projection->isDeleted()
) {
    fwrite(
        STDERR,
        "Повторный Events sync не завершил независимый поток корректно.\n",
    );
    exit(1);
}

if (
    count($transport->calls) !== 4
    || ($transport->calls[0]['query']['updated_since'] ?? null)
        !== '1970-01-01T00:00:00+00:00'
    || isset($transport->calls[0]['query']['after'])
    || ($transport->calls[2]['query']['after'] ?? null)
        !== $remoteEventId
    || isset($transport->calls[3]['query']['after'])
    || $transport->calls[0]['token'] !== $token
) {
    fwrite(
        STDERR,
        "Events worker неверно продолжил составные курсоры.\n",
    );
    exit(1);
}

echo "Federation sync событий и независимое состояние worker проверены\n";
