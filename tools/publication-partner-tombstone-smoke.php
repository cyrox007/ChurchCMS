<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationPartnerTombstoneRepository;
use ChurchCMS\Modules\Publications\PublicationService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'partner-tombstone-smoke';
$root = OrganizationService::fromDatabase()->ensureSiteRoot(
    'Тестовый источник публикаций',
    'diocese',
    $siteKey,
);
$service = PublicationService::fromDatabase();

$publicationId = $service->createDraft(
    title: 'Материал для федеративного удаления',
    syndicationTargets: ['diocese'],
    siteKey: $siteKey,
);
$service->publish(
    $publicationId,
    new DateTimeImmutable(
        '-1 minute',
        new DateTimeZone('UTC'),
    ),
);
$service->withdraw($publicationId);

$repository = PublicationPartnerTombstoneRepository::fromDatabase();
$tombstones = $repository->updatedSince(
    new DateTimeImmutable('2000-01-01T00:00:00Z'),
    $siteKey,
);

if (
    count($tombstones) !== 1
    || $tombstones[0]['publication_public_id'] !== $publicationId
    || $tombstones[0]['organization_owner_public_id']
        !== $root->publicId
    || $tombstones[0]['reason'] !== 'withdrawn'
) {
    fwrite(
        STDERR,
        "Tombstone снятой partner-публикации сохранён некорректно.\n",
    );
    exit(1);
}

$firstUpdatedAt = $tombstones[0]['updated_at']->format('Y-m-d H:i:s');
$service->withdraw($publicationId);

$afterRepeatedWithdraw = $repository->updatedSince(
    new DateTimeImmutable('2000-01-01T00:00:00Z'),
    $siteKey,
);

if (
    count($afterRepeatedWithdraw) !== 1
    || $afterRepeatedWithdraw[0]['updated_at']
        ->format('Y-m-d H:i:s') !== $firstUpdatedAt
) {
    fwrite(
        STDERR,
        "Повторный withdraw ошибочно создал новый tombstone.\n",
    );
    exit(1);
}

$localOnlyId = $service->createDraft(
    title: 'Локальный RSS-материал',
    syndicationTargets: ['rss'],
    siteKey: $siteKey,
);
$service->publish(
    $localOnlyId,
    new DateTimeImmutable(
        '-1 minute',
        new DateTimeZone('UTC'),
    ),
);
$service->withdraw($localOnlyId);

$afterLocalWithdraw = $repository->updatedSince(
    new DateTimeImmutable('2000-01-01T00:00:00Z'),
    $siteKey,
);

if (count($afterLocalWithdraw) !== 1) {
    fwrite(
        STDERR,
        "Локальный материал ошибочно попал в partner tombstones.\n",
    );
    exit(1);
}

echo "Tombstone partner-публикации проверен\n";
