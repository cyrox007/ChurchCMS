<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\FederationProjectionService;
use ChurchCMS\Modules\Organizations\FederationRemoteProjectionRepository;
use ChurchCMS\Modules\Organizations\FederationRepository;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'projection-smoke';
$localRoot = OrganizationService::fromDatabase()->ensureSiteRoot(
    'Тестовый принимающий узел',
    'diocese',
    $siteKey,
);

$remoteInstanceId = '81000000-0000-4000-8000-000000000008';
$remoteOrganizationId = '82000000-0000-4000-8000-000000000008';
$remoteOwnerId = '83000000-0000-4000-8000-000000000008';
$remotePublicationId = '84000000-0000-4000-8000-000000000008';

$federation = FederationService::fromDatabase();
$linkPublicId = $federation->connect(
    localOrganizationPublicId: $localRoot->publicId,
    relation: 'parent',
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteBaseUrl: 'https://remote.example',
    outboundScopes: ['content.read'],
    remoteProfile: 'metropolia',
    remoteName: 'Удалённая митрополия',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $linkPublicId,
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteProfile: 'metropolia',
    remoteName: 'Удалённая митрополия',
    siteKey: $siteKey,
);

$link = FederationRepository::fromDatabase()->findByPublicId(
    $linkPublicId,
    $siteKey,
);
if ($link === null || $link->status !== 'active') {
    fwrite(
        STDERR,
        "Не удалось подготовить активную federation-связь.\n",
    );
    exit(1);
}

$service = FederationProjectionService::fromDatabase();
$repository = FederationRemoteProjectionRepository::fromDatabase();

$created = $service->applyUpsert(
    $linkPublicId,
    'publication',
    [
        'id' => $remotePublicationId,
        'title' => 'Первая версия',
        'organization_owner_id' => $remoteOwnerId,
        'url' => 'https://remote.example/publications/pervaya-versiya',
        'updated_at' => '2026-09-28T10:00:00Z',
    ],
    $siteKey,
);
$projection = $repository->find(
    $link->id,
    'publication',
    $remotePublicationId,
);

if (
    $created !== 'created'
    || $projection === null
    || $projection->state !== 'active'
    || $projection->deleteReason !== null
    || $projection->remoteOwnerOrganizationPublicId
        !== strtolower($remoteOwnerId)
    || ($projection->payload['title'] ?? null)
        !== 'Первая версия'
) {
    fwrite(
        STDERR,
        "Первичная remote projection сохранена некорректно.\n",
    );
    exit(1);
}

$stale = $service->applyUpsert(
    $linkPublicId,
    'publication',
    [
        'id' => $remotePublicationId,
        'title' => 'Устаревшая версия',
        'organization_owner_id' => $remoteOwnerId,
        'url' => 'https://remote.example/publications/ustarevshaya',
        'updated_at' => '2026-09-28T09:59:00Z',
    ],
    $siteKey,
);
$afterStale = $repository->find(
    $link->id,
    'publication',
    $remotePublicationId,
);

if (
    $stale !== 'ignored'
    || $afterStale === null
    || ($afterStale->payload['title'] ?? null)
        !== 'Первая версия'
) {
    fwrite(
        STDERR,
        "Устаревший upsert перезаписал более новую projection.\n",
    );
    exit(1);
}

$equalDelete = $service->applyTombstone(
    $linkPublicId,
    'publication',
    [
        'action' => 'delete',
        'id' => $remotePublicationId,
        'organization_owner_id' => $remoteOwnerId,
        'reason' => 'withdrawn',
        'updated_at' => '2026-09-28T10:00:00Z',
    ],
    $siteKey,
);
$deleted = $repository->find(
    $link->id,
    'publication',
    $remotePublicationId,
);

if (
    $equalDelete !== 'updated'
    || $deleted === null
    || !$deleted->isDeleted()
    || $deleted->deleteReason !== 'withdrawn'
    || $deleted->payload !== []
    || $deleted->canonicalUrl
        !== 'https://remote.example/publications/pervaya-versiya'
) {
    fwrite(
        STDERR,
        "Tombstone с равным временем не получил приоритет.\n",
    );
    exit(1);
}

$equalUpsert = $service->applyUpsert(
    $linkPublicId,
    'publication',
    [
        'id' => $remotePublicationId,
        'title' => 'Не должна воскреснуть',
        'organization_owner_id' => $remoteOwnerId,
        'url' => 'https://remote.example/publications/old',
        'updated_at' => '2026-09-28T10:00:00Z',
    ],
    $siteKey,
);

if (
    $equalUpsert !== 'ignored'
    || !$repository->find(
        $link->id,
        'publication',
        $remotePublicationId,
    )?->isDeleted()
) {
    fwrite(
        STDERR,
        "Upsert с временем tombstone воскресил удалённую projection.\n",
    );
    exit(1);
}

$newerUpsert = $service->applyUpsert(
    $linkPublicId,
    'publication',
    [
        'id' => $remotePublicationId,
        'title' => 'Повторная публикация',
        'organization_owner_id' => $remoteOwnerId,
        'url' => 'https://remote.example/publications/vozvrat',
        'updated_at' => '2026-09-28T10:01:00Z',
    ],
    $siteKey,
);
$restored = $repository->find(
    $link->id,
    'publication',
    $remotePublicationId,
);

if (
    $newerUpsert !== 'updated'
    || $restored === null
    || $restored->state !== 'active'
    || $restored->deleteReason !== null
    || ($restored->payload['title'] ?? null)
        !== 'Повторная публикация'
) {
    fwrite(
        STDERR,
        "Более новый upsert не восстановил projection.\n",
    );
    exit(1);
}

$staleDelete = $service->applyTombstone(
    $linkPublicId,
    'publication',
    [
        'action' => 'delete',
        'id' => $remotePublicationId,
        'organization_owner_id' => $remoteOwnerId,
        'reason' => 'withdrawn',
        'updated_at' => '2026-09-28T10:00:30Z',
    ],
    $siteKey,
);

if ($staleDelete !== 'ignored') {
    fwrite(
        STDERR,
        "Устаревший tombstone перезаписал повторную публикацию.\n",
    );
    exit(1);
}

$newDelete = $service->applyTombstone(
    $linkPublicId,
    'publication',
    [
        'action' => 'delete',
        'id' => $remotePublicationId,
        'organization_owner_id' => $remoteOwnerId,
        'reason' => 'withdrawn',
        'updated_at' => '2026-09-28T10:02:00Z',
    ],
    $siteKey,
);

if (
    $newDelete !== 'updated'
    || $repository->active(
        $link->id,
        'publication',
    ) !== []
) {
    fwrite(
        STDERR,
        "Новый tombstone не скрыл active projection.\n",
    );
    exit(1);
}

$tombstoneOnlyId = '85000000-0000-4000-8000-000000000008';
$tombstoneOnly = $service->applyTombstone(
    $linkPublicId,
    'publication',
    [
        'action' => 'delete',
        'id' => $tombstoneOnlyId,
        'organization_owner_id' => $remoteOwnerId,
        'reason' => 'withdrawn',
        'updated_at' => '2026-09-28T10:03:00Z',
    ],
    $siteKey,
);
$onlyDeleted = $repository->find(
    $link->id,
    'publication',
    $tombstoneOnlyId,
);

if (
    $tombstoneOnly !== 'created'
    || $onlyDeleted === null
    || !$onlyDeleted->isDeleted()
) {
    fwrite(
        STDERR,
        "Tombstone без предварительного full sync не сохранён.\n",
    );
    exit(1);
}

$federation->revoke(
    $linkPublicId,
    $siteKey,
);

try {
    $service->applyUpsert(
        $linkPublicId,
        'publication',
        [
            'id' => $remotePublicationId,
            'title' => 'После revoke',
            'organization_owner_id' => $remoteOwnerId,
            'url' => 'https://remote.example/publications/revoked',
            'updated_at' => '2026-09-28T10:04:00Z',
        ],
        $siteKey,
    );
    fwrite(
        STDERR,
        "Revoked federation link принял remote projection.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Remote projections и tombstones проверены\n";
