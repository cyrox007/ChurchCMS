<?php

declare(strict_types=1);

use ChurchCMS\Core\ThemeContext;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Media\MediaApiResource;
use ChurchCMS\Modules\Media\MediaRepository;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaUploadService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Media image metadata smoke',
    'parish',
);

$png = '/tmp/churchcms-media-image-metadata.png';
$data = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    true,
);

if (!is_string($data) || file_put_contents($png, $data) === false) {
    fwrite(STDERR, "Не удалось подготовить PNG fixture.\n");
    exit(1);
}

$publicId = MediaUploadService::fromConfig()->importFile(
    sourcePath: $png,
    originalName: 'pixel.png',
    ownerOrganizationPublicId: $root->publicId,
    title: 'Пиксель',
    altText: 'Тестовое изображение',
);

$asset = MediaRepository::fromDatabase()->findByPublicId(
    $publicId,
);

if (
    $asset === null
    || $asset->mediaType !== 'image'
    || $asset->mimeType !== 'image/png'
    || $asset->pixelWidth !== 1
    || $asset->pixelHeight !== 1
) {
    fwrite(
        STDERR,
        "Размеры изображения не сохранены из проверенного blob.\n",
    );
    exit(1);
}

$api = (new MediaApiResource($asset))->toApiArray();

if (
    ($api['pixel_width'] ?? null) !== 1
    || ($api['pixel_height'] ?? null) !== 1
    || array_key_exists('original_name', $api)
) {
    fwrite(
        STDERR,
        "Media API projection размеров некорректна.\n",
    );
    exit(1);
}

$manualId = MediaService::fromDatabase()->registerMetadata(
    mediaType: 'document',
    originalName: 'manual.pdf',
    mimeType: 'application/pdf',
    bytes: 10,
    sha256: str_repeat('a', 64),
    ownerOrganizationPublicId: $root->publicId,
);

$manual = MediaRepository::fromDatabase()->findByPublicId(
    $manualId,
);

if (
    $manual === null
    || $manual->pixelWidth !== null
    || $manual->pixelHeight !== null
) {
    fwrite(
        STDERR,
        "Не-image Media неожиданно получил размеры изображения.\n",
    );
    exit(1);
}

try {
    MediaService::fromDatabase()->registerMetadata(
        mediaType: 'document',
        originalName: 'invalid.pdf',
        mimeType: 'application/pdf',
        bytes: 10,
        sha256: str_repeat('b', 64),
        ownerOrganizationPublicId: $root->publicId,
        pixelWidth: 100,
        pixelHeight: 100,
    );
    fwrite(
        STDERR,
        "Размеры изображения ошибочно разрешены для document.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

$renderer = ThemeRenderer::fromConfig();
$theme = new ThemeContext(
    $renderer,
    'default',
);
$assets = [$asset];
$organizationUnits = [$root];
$defaultOwnerPublicId = $root->publicId;
$mediaStatus = null;

ob_start();
require dirname(__DIR__)
    . '/themes/default/templates/admin/media/index.php';
$html = (string) ob_get_clean();

if (!str_contains($html, '1×1 px')) {
    fwrite(
        STDERR,
        "Admin медиатека не показывает размеры изображения.\n",
    );
    exit(1);
}

echo "Media image metadata smoke OK\n";
