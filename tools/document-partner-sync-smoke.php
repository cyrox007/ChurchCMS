<?php

declare(strict_types=1);

use ChurchCMS\Modules\Documents\DocumentApiResource;
use ChurchCMS\Modules\Documents\DocumentPartnerTombstoneApiResource;
use ChurchCMS\Modules\Documents\DocumentPartnerTombstoneRepository;
use ChurchCMS\Modules\Documents\DocumentRepository;
use ChurchCMS\Modules\Documents\DocumentService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use DateTimeImmutable;

require dirname(__DIR__) . '/core.php';

$siteKey = 'document-partner-smoke';
$organizations = OrganizationService::fromDatabase();
$documents = DocumentService::fromDatabase();
$repository = DocumentRepository::fromDatabase();
$tombstones = DocumentPartnerTombstoneRepository::fromDatabase();

$organizations->ensureSiteRoot(
    'Тестовая епархия federation документов',
    'diocese',
    $siteKey,
);

$documentId = $documents->createDraft(
    title: 'Указ для federation',
    siteKey: $siteKey,
    documentType: 'decree',
    documentNumber: '42/2026',
    issuedOn: '2026-09-29',
    summary: 'Безопасная карточка документа без файла.',
);
$documents->publish($documentId, $siteKey);
$documents->setVisibility($documentId, 'federated', $siteKey);

$active = $repository->federatedUpdatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
);
if (count($active) !== 1 || $active[0]->publicId !== $documentId) {
    fwrite(STDERR, "Federation-поток Documents не вернул документ.\n");
    exit(1);
}

$projection = (new DocumentApiResource($active[0]))->toApiArray();
if (
    ($projection['type'] ?? null) !== 'document'
    || ($projection['organization_owner_id'] ?? '') === ''
    || array_key_exists('path', $projection)
    || array_key_exists('file_path', $projection)
    || array_key_exists('blob', $projection)
) {
    fwrite(STDERR, "Documents partner projection раскрывает лишние данные.\n");
    exit(1);
}

$privateId = $documents->createDraft(
    title: 'Приватный документ',
    siteKey: $siteKey,
);
$documents->publish($privateId, $siteKey);

if (count($repository->federatedUpdatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
)) !== 1) {
    fwrite(STDERR, "Приватный документ попал в federation-поток.\n");
    exit(1);
}

$documents->setVisibility($documentId, 'private', $siteKey);
$deleted = $tombstones->updatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
);
if (
    count($deleted) !== 1
    || $deleted[0]['document_public_id'] !== $documentId
    || $deleted[0]['reason'] !== 'visibility_changed'
) {
    fwrite(STDERR, "Смена federation visibility не создала tombstone.\n");
    exit(1);
}

$tombstoneProjection = (
    new DocumentPartnerTombstoneApiResource($deleted[0])
)->toApiArray();
if (
    ($tombstoneProjection['action'] ?? null) !== 'delete'
    || ($tombstoneProjection['type'] ?? null) !== 'document'
) {
    fwrite(STDERR, "Некорректная tombstone projection Documents.\n");
    exit(1);
}

$documents->setVisibility($documentId, 'federated', $siteKey);
if ($tombstones->updatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
) !== []) {
    fwrite(STDERR, "Повторное включение federation не очистило tombstone.\n");
    exit(1);
}

$documents->withdraw($documentId, $siteKey);
$withdrawn = $tombstones->updatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
);
if (
    count($withdrawn) !== 1
    || $withdrawn[0]['reason'] !== 'withdrawn'
) {
    fwrite(STDERR, "Снятие federation-документа не создало tombstone.\n");
    exit(1);
}

$documents->publish($documentId, $siteKey);
$documents->setVisibility($documentId, 'federated', $siteKey);
if ($tombstones->updatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
) !== []) {
    fwrite(STDERR, "Возврат документа в federation не очистил tombstone.\n");
    exit(1);
}

$documents->archive($documentId, $siteKey);
$archived = $tombstones->updatedSince(
    new DateTimeImmutable('1970-01-01T00:00:00Z'),
    $siteKey,
);
if (
    count($archived) !== 1
    || $archived[0]['reason'] !== 'archived'
) {
    fwrite(STDERR, "Архивирование federation-документа не создало tombstone.\n");
    exit(1);
}

try {
    $repository->federatedUpdatedSince(
        new DateTimeImmutable('1970-01-01T00:00:00Z'),
        $siteKey,
        100,
        'not-a-public-id',
    );
    fwrite(STDERR, "Репозиторий принял некорректный cursor public ID.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Documents partner federation source smoke OK\n";
