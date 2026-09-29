<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\FederationRepository;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\FederationSyncDashboardService;
use ChurchCMS\Modules\Organizations\FederationWorkerSyncStateRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'federation-dashboard-smoke';
$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовая епархия dashboard',
    'diocese',
    $siteKey,
);

$federation = FederationService::fromDatabase();

$fullLinkId = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: 'a1100000-0000-4000-8000-000000000001',
    remoteOrganizationPublicId:
        'a1200000-0000-4000-8000-000000000002',
    remoteBaseUrl: 'https://child-dashboard.example',
    inboundScopes: ['content.read'],
    remoteProfile: 'parish',
    remoteName: 'Приход с полным sync',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $fullLinkId,
    remoteInstanceId: 'a1100000-0000-4000-8000-000000000001',
    remoteOrganizationPublicId:
        'a1200000-0000-4000-8000-000000000002',
    remoteProfile: 'parish',
    remoteName: 'Приход с полным sync',
    siteKey: $siteKey,
);

$publicationOnlyId = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: 'b1100000-0000-4000-8000-000000000001',
    remoteOrganizationPublicId:
        'b1200000-0000-4000-8000-000000000002',
    remoteBaseUrl: 'https://publication-dashboard.example',
    inboundScopes: ['publications.read'],
    remoteProfile: 'parish',
    remoteName: 'Приход только публикаций',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $publicationOnlyId,
    remoteInstanceId: 'b1100000-0000-4000-8000-000000000001',
    remoteOrganizationPublicId:
        'b1200000-0000-4000-8000-000000000002',
    remoteProfile: 'parish',
    remoteName: 'Приход только публикаций',
    siteKey: $siteKey,
);

$repository = FederationRepository::fromDatabase();
$fullLink = $repository->findByPublicId(
    $fullLinkId,
    $siteKey,
);
$publicationOnly = $repository->findByPublicId(
    $publicationOnlyId,
    $siteKey,
);

if ($fullLink === null || $publicationOnly === null) {
    fwrite(
        STDERR,
        "Тестовые federation link для dashboard не созданы.\n",
    );
    exit(1);
}

$states = FederationWorkerSyncStateRepository::fromDatabase();
$states->recordSuccess(
    $fullLink->id,
    'publications',
    null,
    'cursor-publications-complete',
);
$states->recordFailure(
    $fullLink->id,
    'events',
    'Тестовая безопасная ошибка Events.',
    null,
);
$states->saveCursor(
    $fullLink->id,
    'worship',
    null,
    'cursor-worship-partial',
);

$snapshot = FederationSyncDashboardService::fromDatabase()
    ->snapshot($siteKey);

$metrics = $snapshot['metrics'] ?? [];
if (
    ($metrics['links'] ?? null) !== 2
    || ($metrics['active_links'] ?? null) !== 2
    || ($metrics['workers'] ?? null) !== 6
    || ($metrics['successful'] ?? null) !== 1
    || ($metrics['failed'] ?? null) !== 1
    || ($metrics['partial'] ?? null) !== 1
    || ($metrics['not_started'] ?? null) !== 3
) {
    fwrite(
        STDERR,
        "Метрики federation sync dashboard рассчитаны неверно.\n",
    );
    exit(1);
}

$fullWorkers = $snapshot['links'][$fullLink->id]['workers']
    ?? [];
$statuses = [];
foreach ($fullWorkers as $worker) {
    $statuses[(string) ($worker['id'] ?? '')] =
        (string) ($worker['status'] ?? '');
}

if (
    ($statuses['publications'] ?? null) !== 'successful'
    || ($statuses['events'] ?? null) !== 'failed'
    || ($statuses['worship'] ?? null) !== 'partial'
    || ($statuses['documents'] ?? null) !== 'not_started'
    || ($statuses['media'] ?? null) !== 'not_started'
) {
    fwrite(
        STDERR,
        "Dashboard потерял отдельные состояния federation worker.\n",
    );
    exit(1);
}

$publicationWorkers =
    $snapshot['links'][$publicationOnly->id]['workers']
    ?? [];

if (
    count($publicationWorkers) !== 1
    || ($publicationWorkers[0]['id'] ?? null)
        !== 'publications'
    || ($publicationWorkers[0]['status'] ?? null)
        !== 'not_started'
) {
    fwrite(
        STDERR,
        "Dashboard неверно применил scopes к federation worker.\n",
    );
    exit(1);
}

$json = json_encode(
    $snapshot,
    JSON_THROW_ON_ERROR
    | JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES,
);

foreach ([
    'cursor-publications-complete',
    'cursor-worship-partial',
] as $secretCursor) {
    if (str_contains($json, $secretCursor)) {
        fwrite(
            STDERR,
            "Dashboard раскрыл содержимое federation sync cursor.\n",
        );
        exit(1);
    }
}

if (!str_contains($json, 'Тестовая безопасная ошибка Events.')) {
    fwrite(
        STDERR,
        "Dashboard потерял безопасную ошибку federation worker.\n",
    );
    exit(1);
}

echo "Метрики federation sync dashboard проверены\n";
