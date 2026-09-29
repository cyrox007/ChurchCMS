<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\FederationPublicationSyncWorker;
use ChurchCMS\Modules\Organizations\FederationRemoteProjectionRepository;
use ChurchCMS\Modules\Organizations\FederationRepository;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\FederationSyncTransport;
use ChurchCMS\Modules\Organizations\FederationWorkerSyncStateRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationRepository;
use ChurchCMS\Modules\Publications\PublicationService;

require dirname(__DIR__) . '/core.php';

final class ScriptedFederationSyncTransport implements FederationSyncTransport
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
                'Тестовый transport получил неожиданный запрос.'
            );
        }

        if (isset($step['error'])) {
            throw new RuntimeException((string) $step['error']);
        }

        $result = $step['result'] ?? null;
        if (!is_array($result)) {
            throw new RuntimeException(
                'Тестовый transport не содержит ответ.'
            );
        }

        return $result;
    }
}

function syncPage(
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

$database = DatabaseManager::getInstance();
$pdo = $database->connection();

$siteKey = 'federation-sync-smoke';
$root = OrganizationService::fromDatabase()->ensureSiteRoot(
    'Тестовый принимающий узел',
    'diocese',
    $siteKey,
);

$remoteInstanceId = '91000000-0000-4000-8000-000000000009';
$remoteOrganizationId = '92000000-0000-4000-8000-000000000009';
$remoteOwnerId = '93000000-0000-4000-8000-000000000009';
$remotePublicationId = '94000000-0000-4000-8000-000000000009';
$token = 'federation-sync-test-token';

$federation = FederationService::fromDatabase();
$linkPublicId = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteBaseUrl: 'https://remote.example',
    inboundScopes: ['content.read'],
    outboundScopes: [],
    outboundToken: $token,
    remoteProfile: 'parish',
    remoteName: 'Удалённый приход',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $linkPublicId,
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteProfile: 'parish',
    remoteName: 'Удалённый приход',
    siteKey: $siteKey,
);

$updatedAt = '2042-01-01T00:00:00+00:00';
$deletedAt = '2042-01-01T00:01:00+00:00';

$transport = new ScriptedFederationSyncTransport([
    [
        'path' => '/api/v1/partner/publications',
        'result' => syncPage(
            [[
                'id' => $remotePublicationId,
                'title' => 'Удалённая публикация',
                'organization_owner_id' => $remoteOwnerId,
                'url' => 'https://remote.example/publications/test',
                'updated_at' => $updatedAt,
            ]],
            $updatedAt,
            $remotePublicationId,
        ),
    ],
    [
        'path' => '/api/v1/partner/publications/tombstones',
        'error' => 'Тестовый сетевой сбой tombstone-потока.',
    ],
    [
        'path' => '/api/v1/partner/publications',
        'result' => syncPage([], null, null),
    ],
    [
        'path' => '/api/v1/partner/publications/tombstones',
        'result' => syncPage(
            [[
                'action' => 'delete',
                'id' => $remotePublicationId,
                'organization_owner_id' => $remoteOwnerId,
                'reason' => 'withdrawn',
                'updated_at' => $deletedAt,
            ]],
            $deletedAt,
            $remotePublicationId,
        ),
    ],
]);

$worker = new FederationPublicationSyncWorker(
    $pdo,
    $transport,
);

$firstRun = $worker->run(
    siteKey: $siteKey,
    pageSize: 1,
);

$linkRepository = FederationRepository::fromDatabase();
$linkAfterFailure = $linkRepository->findByPublicId(
    $linkPublicId,
    $siteKey,
);

if (
    $firstRun['failed'] !== 1
    || $firstRun['succeeded'] !== 0
    || $linkAfterFailure === null
    || $linkAfterFailure->status !== 'active'
    || $linkAfterFailure->lastSyncError === null
    || $linkAfterFailure->lastSyncAt !== null
    || $linkAfterFailure->syncCursor === null
) {
    fwrite(
        STDERR,
        "Worker некорректно сохранил частичный сбой синхронизации.\n",
    );
    exit(1);
}

$partialCursor = json_decode(
    $linkAfterFailure->syncCursor,
    true,
    32,
    JSON_THROW_ON_ERROR,
);

if (
    ($partialCursor['publications']['after'] ?? null)
        !== $remotePublicationId
    || ($partialCursor['tombstones']['after'] ?? null)
        !== null
) {
    fwrite(
        STDERR,
        "При сбое tombstone-потока безопасный курсор сохранён неверно.\n",
    );
    exit(1);
}

$secondRun = $worker->run(
    siteKey: $siteKey,
    pageSize: 1,
);

$linkAfterRetry = $linkRepository->findByPublicId(
    $linkPublicId,
    $siteKey,
);
$linkId = $linkAfterRetry?->id ?? 0;
$projection = $linkId > 0
    ? FederationRemoteProjectionRepository::fromDatabase()->find(
        $linkId,
        'publication',
        $remotePublicationId,
    )
    : null;

if (
    $secondRun['succeeded'] !== 1
    || $secondRun['failed'] !== 0
    || $secondRun['tombstones'] !== 1
    || $linkAfterRetry === null
    || $linkAfterRetry->lastSyncError !== null
    || $linkAfterRetry->lastSyncAt === null
    || $projection === null
    || !$projection->isDeleted()
) {
    fwrite(
        STDERR,
        "Повторный federation sync не завершил сохранённый поток.\n",
    );
    exit(1);
}

$cursorAfterRetry = $linkAfterRetry->syncCursor;
if ($cursorAfterRetry === null) {
    fwrite(
        STDERR,
        "После успешной синхронизации отсутствует курсор.\n",
    );
    exit(1);
}

$syncStates = FederationWorkerSyncStateRepository::fromDatabase();

try {
    $syncStates->saveCursor(
        $linkAfterRetry->id,
        'publications',
        '{"version":1,"stale":true}',
        $cursorAfterRetry,
    );
    fwrite(
        STDERR,
        "Устаревший параллельный процесс смог перезаписать sync-курсор worker.\n",
    );
    exit(1);
} catch (RuntimeException) {
}

$linkAfterConflict = $linkRepository->findByPublicId(
    $linkPublicId,
    $siteKey,
);
$stateAfterConflict = $syncStates->state(
    $linkAfterRetry->id,
    'publications',
);
if (
    $linkAfterConflict === null
    || $linkAfterConflict->syncCursor !== $cursorAfterRetry
    || $linkAfterConflict->lastSyncError !== null
    || ($stateAfterConflict['cursor'] ?? null)
        !== $cursorAfterRetry
    || ($stateAfterConflict['last_sync_error'] ?? null)
        !== null
) {
    fwrite(
        STDERR,
        "Конкурентная защита повредила актуальное sync-состояние.\n",
    );
    exit(1);
}

if (
    count($transport->calls) !== 4
    || ($transport->calls[0]['query']['updated_since'] ?? null)
        !== '1970-01-01T00:00:00+00:00'
    || isset($transport->calls[0]['query']['after'])
    || ($transport->calls[2]['query']['after'] ?? null)
        !== $remotePublicationId
    || isset($transport->calls[3]['query']['after'])
    || $transport->calls[0]['token'] !== $token
) {
    fwrite(
        STDERR,
        "Worker неверно продолжил синхронизацию по сохранённым курсорам.\n",
    );
    exit(1);
}

$cursorSiteKey = 'publication-cursor-smoke';
OrganizationService::fromDatabase()->ensureSiteRoot(
    'Тестовый источник публикаций',
    'diocese',
    $cursorSiteKey,
);
$publicationService = PublicationService::fromDatabase();
$firstPublicationId = $publicationService->createDraft(
    title: 'Первая публикация с одинаковым временем',
    syndicationTargets: ['diocese'],
    siteKey: $cursorSiteKey,
);
$secondPublicationId = $publicationService->createDraft(
    title: 'Вторая публикация с одинаковым временем',
    syndicationTargets: ['diocese'],
    siteKey: $cursorSiteKey,
);
$publishedAt = new DateTimeImmutable(
    '-5 minutes',
    new DateTimeZone('UTC'),
);
$publicationService->publish(
    $firstPublicationId,
    $publishedAt,
);
$publicationService->publish(
    $secondPublicationId,
    $publishedAt,
);

$fixedTimestamp = '2026-01-01 00:00:00';
$normalize = $pdo->prepare(
    'UPDATE publications
     SET updated_at = :updated_at
     WHERE site_key = :site_key
       AND public_id IN (:first_id, :second_id)'
);
$normalize->execute([
    'updated_at' => $fixedTimestamp,
    'site_key' => $cursorSiteKey,
    'first_id' => $firstPublicationId,
    'second_id' => $secondPublicationId,
]);

$publications = PublicationRepository::fromDatabase();
$pageOne = $publications->publishedUpdatedSince(
    new DateTimeImmutable('2000-01-01T00:00:00Z'),
    $cursorSiteKey,
    1,
);
$pageTwo = $publications->publishedUpdatedSince(
    $pageOne[0]->updatedAt,
    $cursorSiteKey,
    1,
    $pageOne[0]->publicId,
);

if (
    count($pageOne) !== 1
    || count($pageTwo) !== 1
    || $pageOne[0]->publicId === $pageTwo[0]->publicId
) {
    fwrite(
        STDERR,
        "Составной курсор публикаций пропустил запись с тем же временем.\n",
    );
    exit(1);
}

echo "Federation sync публикаций и безопасные курсоры проверены\n";
