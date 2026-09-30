<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Modules\Media\MediaGalleryRepository;
use ChurchCMS\Modules\Media\MediaGalleryService;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaUsageService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход галерей',
    'parish',
);

$media = MediaService::fromDatabase();

$imageA = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'a.png',
    mimeType: 'image/png',
    bytes: 100,
    sha256: str_repeat('a', 64),
    ownerOrganizationPublicId: $root->publicId,
    pixelWidth: 800,
    pixelHeight: 600,
);
$imageB = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'b.jpg',
    mimeType: 'image/jpeg',
    bytes: 200,
    sha256: str_repeat('b', 64),
    ownerOrganizationPublicId: $root->publicId,
    pixelWidth: 1200,
    pixelHeight: 800,
);
$document = $media->registerMetadata(
    mediaType: 'document',
    originalName: 'document.pdf',
    mimeType: 'application/pdf',
    bytes: 300,
    sha256: str_repeat('c', 64),
    ownerOrganizationPublicId: $root->publicId,
);

$capability = ModuleRuntimeLoader::capability(
    'media',
    'media.galleries',
);
if (
    $capability === null
    || !method_exists($capability, 'items')
) {
    fwrite(
        STDERR,
        "Capability media.galleries не зарегистрирован.\n",
    );
    exit(1);
}

$service = MediaGalleryService::fromDatabase();

$empty = $service->createDraft(
    title: 'Пустая галерея',
    ownerOrganizationPublicId: $root->publicId,
);

try {
    $service->publish($empty);
    fwrite(
        STDERR,
        "Пустая галерея ошибочно опубликована.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

$gallery = $service->createDraft(
    title: 'Праздничная служба',
    ownerOrganizationPublicId: $root->publicId,
    description: 'Тестовая упорядоченная галерея.',
);

try {
    $service->setVisibility(
        $gallery,
        'public',
    );
    fwrite(
        STDERR,
        "Draft-галерея ошибочно стала public.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

$service->replaceItems(
    $gallery,
    [$imageB, $imageA],
);

$items = $service->items($gallery);
if (
    count($items) !== 2
    || $items[0]->publicId !== $imageB
    || $items[1]->publicId !== $imageA
) {
    fwrite(
        STDERR,
        "Порядок изображений галереи не сохранён.\n",
    );
    exit(1);
}

try {
    $service->replaceItems(
        $gallery,
        [$imageA, $imageA],
    );
    fwrite(
        STDERR,
        "Дубликат изображения ошибочно принят.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

$items = $service->items($gallery);
if (
    count($items) !== 2
    || $items[0]->publicId !== $imageB
    || $items[1]->publicId !== $imageA
) {
    fwrite(
        STDERR,
        "Неудачная замена изменила существующий порядок.\n",
    );
    exit(1);
}

try {
    $service->replaceItems(
        $gallery,
        [$document],
    );
    fwrite(
        STDERR,
        "Document Media ошибочно принят в галерею.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

try {
    $media->archive($imageB);
    fwrite(
        STDERR,
        "Используемое галереей изображение ошибочно архивировано.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

$references = MediaUsageService::fromDatabase()
    ->forConsumer(
        'gallery',
        $gallery,
    );

if (
    count($references) !== 2
    || $references[0]->usageKey !== 'item:000001'
    || $references[0]->mediaPublicId !== $imageB
    || $references[1]->usageKey !== 'item:000002'
    || $references[1]->mediaPublicId !== $imageA
) {
    fwrite(
        STDERR,
        "Usage slots галереи сформированы неверно.\n",
    );
    exit(1);
}

$service->publish($gallery);
$service->setVisibility(
    $gallery,
    'public',
);

$public = MediaGalleryRepository::fromDatabase()
    ->publicPublished();

if (
    count($public) !== 1
    || $public[0]->publicId !== $gallery
) {
    fwrite(
        STDERR,
        "Опубликованная public-галерея не появилась в репозитории.\n",
    );
    exit(1);
}

$service->withdraw($gallery);

if (
    MediaGalleryRepository::fromDatabase()
        ->publicPublished() !== []
) {
    fwrite(
        STDERR,
        "Withdraw не убрал галерею из public-списка.\n",
    );
    exit(1);
}

$service->archive($gallery);

if (
    $service->items($gallery) !== []
    || MediaUsageService::fromDatabase()
        ->forConsumer(
            'gallery',
            $gallery,
        ) !== []
) {
    fwrite(
        STDERR,
        "Archive галереи не снял usage references.\n",
    );
    exit(1);
}

$media->archive($imageB);

echo "Media gallery foundation smoke OK\n";
