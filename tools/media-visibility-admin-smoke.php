<?php

declare(strict_types=1);

use ChurchCMS\Core\Router;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Media\MediaPartnerTombstoneRepository;
use ChurchCMS\Modules\Media\MediaRepository;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход Media visibility',
    'parish',
);

$service = MediaService::fromDatabase();
$publicId = $service->registerMetadata(
    mediaType: 'image',
    originalName: 'visibility.png',
    mimeType: 'image/png',
    bytes: 123,
    sha256: str_repeat('d', 64),
    ownerOrganizationPublicId: $root->publicId,
    pixelWidth: 640,
    pixelHeight: 480,
    title: 'Тест видимости',
);

$repository = MediaRepository::fromDatabase();
$asset = $repository->findByPublicId($publicId);

if (
    $asset === null
    || $asset->visibility !== 'private'
) {
    fwrite(STDERR, "Media asset не создан private.\n");
    exit(1);
}

$service->setVisibility(
    $publicId,
    'public',
);
$asset = $repository->findByPublicId($publicId);

if ($asset === null || $asset->visibility !== 'public') {
    fwrite(STDERR, "Media visibility public не сохранилась.\n");
    exit(1);
}

$service->setVisibility(
    $publicId,
    'federated',
);
$asset = $repository->findByPublicId($publicId);

if ($asset === null || $asset->visibility !== 'federated') {
    fwrite(STDERR, "Media visibility federated не сохранилась.\n");
    exit(1);
}

$service->setVisibility(
    $publicId,
    'private',
);
$asset = $repository->findByPublicId($publicId);

if ($asset === null || $asset->visibility !== 'private') {
    fwrite(STDERR, "Media visibility private не восстановилась.\n");
    exit(1);
}

$tombstones = MediaPartnerTombstoneRepository::fromDatabase()
    ->updatedSince(
        new \DateTimeImmutable('1970-01-01T00:00:00Z'),
        'default',
        10,
        null,
    );

if (
    count($tombstones) !== 1
    || ($tombstones[0]['media_public_id'] ?? null)
        !== $publicId
) {
    fwrite(
        STDERR,
        "Выход из federated не создал ожидаемый tombstone.\n",
    );
    exit(1);
}

$renderer = ThemeRenderer::fromConfig();
$html = $renderer->capture(
    'admin.media.index',
    [
        'assets' => [$asset],
        'organizationUnits' => [$root],
        'defaultOwnerPublicId' => $root->publicId,
        'mediaStatus' => null,
    ],
);

foreach ([
    'Медиатека',
    'Сохранить видимость',
    'name="visibility"',
    'Публично на сайте',
    'Для федерации',
    'name="csrf_token"',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(
            STDERR,
            "Media visibility UI не содержит: {$expected}\n",
        );
        exit(1);
    }
}

$route = Router::getInstance()->url(
    'admin_media_visibility',
    ['publicId' => $publicId],
);

if (
    $route !== '/admin/media/'
        . rawurlencode($publicId)
        . '/visibility'
) {
    fwrite(STDERR, "Маршрут Media visibility неверен.\n");
    exit(1);
}

echo "Media visibility Admin Shell smoke OK\n";
