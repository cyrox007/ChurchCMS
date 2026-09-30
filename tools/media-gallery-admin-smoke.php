<?php

declare(strict_types=1);

use ChurchCMS\Core\Router;
use ChurchCMS\Core\ThemeContext;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Media\MediaGalleryRepository;
use ChurchCMS\Modules\Media\MediaGalleryService;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход Admin gallery',
    'parish',
);
$allowedPublicId = $organizations->create(
    name: 'Доступный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
);
$hiddenPublicId = $organizations->create(
    name: 'Скрытый отдел',
    type: 'department',
    parentPublicId: $root->publicId,
);

$organizationRepository = OrganizationRepository::fromDatabase();
$allowed = $organizationRepository->findByPublicId(
    $allowedPublicId,
);
$hidden = $organizationRepository->findByPublicId(
    $hiddenPublicId,
);

if ($allowed === null || $hidden === null) {
    fwrite(STDERR, "Организации gallery admin smoke не найдены.\n");
    exit(1);
}

$media = MediaService::fromDatabase();

$imageA = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'allowed-a.png',
    mimeType: 'image/png',
    bytes: 100,
    sha256: str_repeat('a', 64),
    ownerOrganizationPublicId: $allowed->publicId,
    pixelWidth: 800,
    pixelHeight: 600,
    title: 'Первое изображение',
);
$imageB = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'allowed-b.jpg',
    mimeType: 'image/jpeg',
    bytes: 200,
    sha256: str_repeat('b', 64),
    ownerOrganizationPublicId: $allowed->publicId,
    pixelWidth: 1200,
    pixelHeight: 800,
    title: 'Второе изображение',
);
$hiddenImage = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'hidden.png',
    mimeType: 'image/png',
    bytes: 300,
    sha256: str_repeat('c', 64),
    ownerOrganizationPublicId: $hidden->publicId,
    pixelWidth: 640,
    pixelHeight: 480,
    title: 'Скрытое изображение',
);

$service = MediaGalleryService::fromDatabase();
$repository = MediaGalleryRepository::fromDatabase();

$gallery = $service->createDraft(
    title: 'Доступная галерея',
    ownerOrganizationPublicId: $allowed->publicId,
    description: 'Исходное описание',
);
$hiddenGallery = $service->createDraft(
    title: 'Скрытая галерея',
    ownerOrganizationPublicId: $hidden->publicId,
);

$service->replaceItems(
    $gallery,
    [$imageB, $imageA],
);
$service->replaceItems(
    $hiddenGallery,
    [$hiddenImage],
);

$adminList = $repository->adminList([
    $allowed->publicId,
]);

if (
    count($adminList) !== 1
    || $adminList[0]->publicId !== $gallery
) {
    fwrite(
        STDERR,
        "Scoped adminList галерей показал чужую организацию.\n",
    );
    exit(1);
}

$service->updateDetails(
    $gallery,
    'Обновлённая галерея',
    'Новое описание',
);

$updated = $repository->findByPublicId($gallery);

if (
    $updated === null
    || $updated->title !== 'Обновлённая галерея'
    || $updated->description !== 'Новое описание'
) {
    fwrite(
        STDERR,
        "Редактирование карточки галереи не сохранилось.\n",
    );
    exit(1);
}

$items = $service->items($gallery);

if (
    count($items) !== 2
    || $items[0]->publicId !== $imageB
    || $items[1]->publicId !== $imageA
) {
    fwrite(
        STDERR,
        "Порядок изображений галереи потерян.\n",
    );
    exit(1);
}

$router = Router::getInstance();
$routes = [
    'admin_media_galleries' =>
        '/admin/media/galleries',
    'admin_media_galleries_create' =>
        '/admin/media/galleries',
    'admin_media_galleries_update' =>
        '/admin/media/galleries/' . rawurlencode($gallery),
    'admin_media_galleries_publish' =>
        '/admin/media/galleries/' . rawurlencode($gallery)
        . '/publish',
    'admin_media_galleries_withdraw' =>
        '/admin/media/galleries/' . rawurlencode($gallery)
        . '/withdraw',
    'admin_media_galleries_archive' =>
        '/admin/media/galleries/' . rawurlencode($gallery)
        . '/archive',
];

foreach ($routes as $name => $expected) {
    $params = str_contains($name, '_create')
        || $name === 'admin_media_galleries'
        ? []
        : ['publicId' => $gallery];

    if ($router->url($name, $params) !== $expected) {
        fwrite(
            STDERR,
            "Маршрут {$name} зарегистрирован неверно.\n",
        );
        exit(1);
    }
}

$allAssets = \ChurchCMS\Modules\Media\MediaRepository::fromDatabase()
    ->adminList([$allowed->publicId]);
$images = array_values(array_filter(
    $allAssets,
    static fn($asset): bool =>
        $asset->mediaType === 'image'
        && $asset->status !== 'archived',
));

$renderer = ThemeRenderer::fromConfig();
$theme = new ThemeContext(
    $renderer,
    'default',
);
$galleries = [
    [
        'gallery' => $updated,
        'items' => $items,
    ],
];
$organizationUnits = [$allowed];
$defaultOwnerPublicId = $allowed->publicId;
$galleryStatus = null;

ob_start();
require dirname(__DIR__)
    . '/themes/default/templates/admin/media/galleries.php';
$html = (string) ob_get_clean();

foreach ([
    'Галереи',
    'Обновлённая галерея',
    'Первое изображение',
    'Второе изображение',
    'name="gallery_items[]"',
    'name="csrf_token"',
    'Опубликовать',
    'Архивировать',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(
            STDERR,
            "Admin gallery template не содержит: {$expected}\n",
        );
        exit(1);
    }
}

if (
    str_contains($html, 'Скрытая галерея')
    || str_contains($html, 'Скрытое изображение')
) {
    fwrite(
        STDERR,
        "Admin gallery template получил данные чужой организации.\n",
    );
    exit(1);
}

echo "Media gallery Admin Shell smoke OK\n";
