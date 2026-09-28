<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
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

$secondPartnerId = $service->createDraft(
    title: 'Второй материал для проверки курсора',
    syndicationTargets: ['diocese'],
    siteKey: $siteKey,
);
$service->publish(
    $secondPartnerId,
    new DateTimeImmutable(
        '-1 minute',
        new DateTimeZone('UTC'),
    ),
);
$service->withdraw($secondPartnerId);

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

if (count($afterLocalWithdraw) !== 2) {
    fwrite(
        STDERR,
        "Локальный материал ошибочно попал в partner tombstones.\n",
    );
    exit(1);
}

$pdo = DatabaseManager::getInstance()->connection();
$fixedTimestamp = '2040-01-01 00:00:00';
$normalize = $pdo->prepare(
    'UPDATE publication_partner_tombstones
     SET withdrawn_at = :withdrawn_at,
         updated_at = :updated_at
     WHERE site_key = :site_key
       AND publication_public_id IN (:first_id, :second_id)'
);
$normalize->execute([
    'withdrawn_at' => $fixedTimestamp,
    'updated_at' => $fixedTimestamp,
    'site_key' => $siteKey,
    'first_id' => $publicationId,
    'second_id' => $secondPartnerId,
]);

$pageOne = $repository->updatedSince(
    new DateTimeImmutable('2000-01-01T00:00:00Z'),
    $siteKey,
    1,
);
$pageTwo = $repository->updatedSince(
    $pageOne[0]['updated_at'],
    $siteKey,
    1,
    $pageOne[0]['publication_public_id'],
);

if (
    count($pageOne) !== 1
    || count($pageTwo) !== 1
    || $pageOne[0]['publication_public_id']
        === $pageTwo[0]['publication_public_id']
) {
    fwrite(
        STDERR,
        "Tie-breaker tombstone-потока пропустил запись с тем же временем.\n",
    );
    exit(1);
}

echo "Tombstone partner-публикации проверен\n";
