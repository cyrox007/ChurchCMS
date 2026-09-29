<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Media\MediaRepository;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$media = MediaService::fromDatabase();
$repository = MediaRepository::fromDatabase();

$siteKey = 'media-smoke';
$root = $organizations->ensureSiteRoot(
    'Тестовая епархия медиатеки',
    'diocese',
    $siteKey,
);
$departmentId = $organizations->create(
    name: 'Информационный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$checksum = hash('sha256', 'churchcms-media-smoke');

$rootAssetId = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'sobor.jpg',
    mimeType: 'image/jpeg',
    bytes: 12345,
    sha256: $checksum,
    siteKey: $siteKey,
    title: 'Кафедральный собор',
    altText: 'Фасад кафедрального собора',
);
$rootAsset = $repository->findByPublicId(
    $rootAssetId,
    $siteKey,
);

if (
    $rootAsset === null
    || $rootAsset->ownerOrganizationPublicId !== $root->publicId
    || $rootAsset->mimeType !== 'image/jpeg'
    || $rootAsset->sha256 !== $checksum
    || $rootAsset->status !== 'registered'
    || $rootAsset->visibility !== 'private'
) {
    fwrite(
        STDERR,
        "Медиаматериал не получил корректные метаданные/root-владельца.\n",
    );
    exit(1);
}

$media->setVisibility(
    $rootAssetId,
    'federated',
    $siteKey,
);
$federatedMedia = $repository->forFederation($siteKey);
if (
    count($federatedMedia) !== 1
    || $federatedMedia[0]->publicId !== $rootAssetId
) {
    fwrite(STDERR, "Media federation visibility не отфильтрована.\n");
    exit(1);
}

$departmentAssetId = $media->registerMetadata(
    mediaType: 'document',
    originalName: 'announcement.pdf',
    mimeType: 'application/pdf',
    bytes: 9876,
    sha256: hash('sha256', 'department-document'),
    ownerOrganizationPublicId: $departmentId,
    siteKey: $siteKey,
);
$departmentAssets = $repository->forOrganization(
    $departmentId,
    $siteKey,
);

if (
    count($departmentAssets) !== 1
    || $departmentAssets[0]->publicId !== $departmentAssetId
) {
    fwrite(
        STDERR,
        "Репозиторий не вернул медиаматериал выбранной организации.\n",
    );
    exit(1);
}

$media->assignOrganizationOwner(
    $rootAssetId,
    $departmentId,
    $siteKey,
);
$moved = $repository->findByPublicId(
    $rootAssetId,
    $siteKey,
);

if (
    $moved === null
    || $moved->ownerOrganizationPublicId !== $departmentId
) {
    fwrite(
        STDERR,
        "Смена владельца медиаматериала не сохранилась.\n",
    );
    exit(1);
}

$media->archive(
    $departmentAssetId,
    $siteKey,
);
$archived = $repository->findByPublicId(
    $departmentAssetId,
    $siteKey,
);

if ($archived?->status !== 'archived') {
    fwrite(
        STDERR,
        "Архивирование медиаматериала не сохранилось.\n",
    );
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужая епархия медиатеки',
    'diocese',
    'media-foreign',
);

try {
    $media->registerMetadata(
        mediaType: 'image',
        originalName: 'foreign.jpg',
        mimeType: 'image/jpeg',
        bytes: 1,
        sha256: hash('sha256', 'foreign'),
        ownerOrganizationPublicId: $foreignRoot->publicId,
        siteKey: $siteKey,
    );
    fwrite(
        STDERR,
        "Сервис разрешил владельца Media из другого site_key.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

foreach ([
    ['mime' => 'invalid mime', 'sha' => $checksum],
    ['mime' => 'image/jpeg', 'sha' => 'not-sha256'],
] as $invalid) {
    try {
        $media->registerMetadata(
            mediaType: 'image',
            originalName: 'invalid.bin',
            mimeType: $invalid['mime'],
            bytes: 1,
            sha256: $invalid['sha'],
            siteKey: $siteKey,
        );
        fwrite(
            STDERR,
            "Сервис принял некорректные Media metadata.\n",
        );
        exit(1);
    } catch (InvalidArgumentException) {
    }
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE media_assets
     SET owner_organization_public_id = :organization_id
     WHERE public_id = :public_id
       AND site_key = :site_key'
);

try {
    $statement->execute([
        'organization_id' => $foreignRoot->publicId,
        'public_id' => $rootAssetId,
        'site_key' => $siteKey,
    ]);
    fwrite(
        STDERR,
        "База разрешила владельца Media из другого site_key.\n",
    );
    exit(1);
} catch (PDOException) {
}

echo "Media organization foundation smoke OK\n";
