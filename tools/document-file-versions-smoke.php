<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Documents\DocumentFileVersionService;
use ChurchCMS\Modules\Documents\DocumentService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$pdo = DatabaseManager::getInstance()->connection();
$root = OrganizationService::fromDatabase()->ensureSiteRoot(
    'Тестовый приход версий документов',
    'parish',
);

$documentId = DocumentService::fromDatabase()->createDraft(
    title: 'Положение о приходе',
    ownerOrganizationPublicId: $root->publicId,
);

$mediaIds = [
    Uuid::v4(),
    Uuid::v4(),
];
$insertMedia = $pdo->prepare(
    'INSERT INTO media_assets (
        public_id,
        site_key,
        owner_organization_public_id,
        status,
        visibility,
        media_type,
        original_name,
        mime_type,
        bytes,
        sha256,
        title,
        alt_text,
        created_at,
        updated_at
     ) VALUES (
        :public_id,
        :site_key,
        :owner,
        :status,
        :visibility,
        :media_type,
        :original_name,
        :mime_type,
        :bytes,
        :sha256,
        :title,
        NULL,
        :created_at,
        :updated_at
     )'
);

foreach ($mediaIds as $index => $mediaId) {
    $now = gmdate('Y-m-d H:i:s');
    $insertMedia->execute([
        'public_id' => $mediaId,
        'site_key' => 'default',
        'owner' => $root->publicId,
        'status' => 'registered',
        'visibility' => 'private',
        'media_type' => 'document',
        'original_name' => 'version-' . ($index + 1) . '.pdf',
        'mime_type' => 'application/pdf',
        'bytes' => 128 + $index,
        'sha256' => str_repeat(
            $index === 0 ? 'a' : 'b',
            64,
        ),
        'title' => 'Версия ' . ($index + 1),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

$versions = DocumentFileVersionService::fromDatabase();
$first = $versions->record(
    $documentId,
    $mediaIds[0],
    'Первоначальная редакция',
);
$second = $versions->record(
    $documentId,
    $mediaIds[1],
    'Исправленная редакция',
);

if (
    $first->versionNumber !== 1
    || $second->versionNumber !== 2
    || $first->mediaPublicId !== $mediaIds[0]
    || $second->mediaPublicId !== $mediaIds[1]
) {
    throw new RuntimeException(
        'Нарушена последовательность версий файла документа.'
    );
}

$list = $versions->forDocument($documentId);
if (
    count($list) !== 2
    || $list[0]->versionNumber !== 2
    || $list[1]->versionNumber !== 1
) {
    throw new RuntimeException(
        'История версий файла возвращается в неверном порядке.'
    );
}

$usage = $pdo->prepare(
    'SELECT COUNT(*)
     FROM media_usage_references
     WHERE site_key = :site_key
       AND consumer_type = :consumer_type
       AND usage_key = :usage_key'
);
$usage->execute([
    'site_key' => 'default',
    'consumer_type' => 'document-version',
    'usage_key' => 'file',
]);

if ((int) $usage->fetchColumn() !== 2) {
    throw new RuntimeException(
        'Исторические файлы не защищены Media usage-ссылками.'
    );
}

echo "Версии файлов документов: OK\n";
