<?php

declare(strict_types=1);

use ChurchCMS\Modules\Media\MediaApiResource;
use ChurchCMS\Modules\Media\MediaPartnerTombstoneApiResource;
use ChurchCMS\Modules\Media\MediaPartnerTombstoneRepository;
use ChurchCMS\Modules\Media\MediaRepository;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use DateTimeImmutable;

require dirname(__DIR__) . '/core.php';

$siteKey = 'media-partner-smoke';
$organizations = OrganizationService::fromDatabase();
$media = MediaService::fromDatabase();
$repository = MediaRepository::fromDatabase();
$tombstones = MediaPartnerTombstoneRepository::fromDatabase();

$organizations->ensureSiteRoot(
    'Тестовая епархия federation Media',
    'diocese',
    $siteKey,
);

$assetId = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'internal-source-name.jpg',
    mimeType: 'image/jpeg',
    bytes: 12345,
    sha256: hash('sha256', 'media-partner-smoke'),
    siteKey: $siteKey,
    title: 'Кафедральный собор',
    altText: 'Фасад собора',
);
$media->setVisibility($assetId, 'federated', $siteKey);

$active = $repository->federatedUpdatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
);
if (count($active) !== 1 || $active[0]->publicId !== $assetId) {
    fwrite(STDERR, "Federation-поток Media не вернул запись.\n");
    exit(1);
}

$projection = (new MediaApiResource($active[0]))->toApiArray();
if (
    ($projection['type'] ?? null) !== 'media'
    || ($projection['blob_available'] ?? true) !== false
    || ($projection['organization_owner_id'] ?? '') === ''
    || array_key_exists('original_name', $projection)
    || array_key_exists('path', $projection)
    || array_key_exists('file_path', $projection)
    || array_key_exists('blob', $projection)
) {
    fwrite(STDERR, "Media partner projection раскрывает лишние данные.\n");
    exit(1);
}

$privateId = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'private.jpg',
    mimeType: 'image/jpeg',
    bytes: 1,
    sha256: hash('sha256', 'private-media'),
    siteKey: $siteKey,
);
if (count($repository->federatedUpdatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
)) !== 1) {
    fwrite(STDERR, "Private Media попал в federation-поток.\n");
    exit(1);
}

$media->setVisibility($assetId, 'private', $siteKey);
$deleted = $tombstones->updatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
);
if (
    count($deleted) !== 1
    || $deleted[0]['media_public_id'] !== $assetId
    || $deleted[0]['reason'] !== 'visibility_changed'
) {
    fwrite(STDERR, "Смена Media visibility не создала tombstone.\n");
    exit(1);
}

$tombstoneProjection = (
    new MediaPartnerTombstoneApiResource($deleted[0])
)->toApiArray();
if (
    ($tombstoneProjection['action'] ?? null) !== 'delete'
    || ($tombstoneProjection['type'] ?? null) !== 'media'
) {
    fwrite(STDERR, "Некорректная tombstone projection Media.\n");
    exit(1);
}

$media->setVisibility($assetId, 'federated', $siteKey);
if ($tombstones->updatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
) !== []) {
    fwrite(STDERR, "Возврат Media в federation не очистил tombstone.\n");
    exit(1);
}

$media->archive($assetId, $siteKey);
$archived = $tombstones->updatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
);
if (
    count($archived) !== 1
    || $archived[0]['reason'] !== 'archived'
) {
    fwrite(STDERR, "Архивирование federated Media не создало tombstone.\n");
    exit(1);
}

try {
    $repository->federatedUpdatedSince(
        new DateTimeImmutable('1970-01-01T00:00:00Z'),
        $siteKey,
        100,
        'not-a-public-id',
    );
    fwrite(STDERR, "Media repository принял некорректный cursor public ID.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Media partner federation source smoke OK\n";
