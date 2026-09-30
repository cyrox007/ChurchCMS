<?php

declare(strict_types=1);

use ChurchCMS\Modules\Media\MediaBlobStorage;
use ChurchCMS\Modules\Media\MediaRepository;
use ChurchCMS\Modules\Media\MediaUploadService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$root = OrganizationService::fromDatabase()->ensureSiteRoot(
    'Тестовый приход Media storage',
    'parish',
);

$storageRoot = '/tmp/churchcms-media-safe-storage';
$sourceDir = '/tmp/churchcms-media-safe-source';

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }

    @rmdir($path);
};

$removeTree($storageRoot);
$removeTree($sourceDir);

if (!mkdir($sourceDir, 0700, true) && !is_dir($sourceDir)) {
    throw new RuntimeException('Не удалось создать исходный smoke-каталог.');
}

$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    true,
);

if (!is_string($png)) {
    exit(1);
}

$spoofedPng = $sourceDir . '/dangerous.php';
$htmlAsJpg = $sourceDir . '/photo.jpg';

file_put_contents($spoofedPng, $png);
file_put_contents(
    $htmlAsJpg,
    '<html><script>alert(1)</script></html>',
);

$storage = new MediaBlobStorage(
    $storageRoot,
    1024 * 1024,
    dirname(__DIR__),
);

$first = $storage->storeFile($spoofedPng);

if (
    $first->mimeType !== 'image/png'
    || $first->mediaType !== 'image'
    || $first->bytes !== strlen($png)
    || !$first->created
    || !$storage->exists($first->sha256)
) {
    fwrite(
        STDERR,
        "Media storage неверно определил PNG по содержимому.\n",
    );
    exit(1);
}

$storedPath = $storage->pathForHash(
    $first->sha256,
);

if (
    !str_starts_with(
        $storedPath,
        $storageRoot . DIRECTORY_SEPARATOR,
    )
    || !str_ends_with($storedPath, '.blob')
    || str_contains($storedPath, '.php')
    || file_get_contents($storedPath) !== $png
) {
    fwrite(
        STDERR,
        "Media blob сохранён по небезопасному пути.\n",
    );
    exit(1);
}

$second = $storage->storeFile($spoofedPng);
if (
    $second->created
    || $second->sha256 !== $first->sha256
) {
    fwrite(
        STDERR,
        "Повторный Media blob не был дедуплицирован.\n",
    );
    exit(1);
}

try {
    $storage->storeFile($htmlAsJpg);
    fwrite(
        STDERR,
        "HTML с расширением JPG ошибочно принят Media storage.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    new MediaBlobStorage(
        dirname(__DIR__) . '/storage/media-unsafe',
        1024 * 1024,
        dirname(__DIR__),
    );
    fwrite(
        STDERR,
        "Media storage внутри корня ChurchCMS ошибочно разрешён.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$upload = MediaUploadService::fromConfig();
$publicId = $upload->importFile(
    sourcePath: $spoofedPng,
    originalName: '../../unsafe-name.php',
    ownerOrganizationPublicId: $root->publicId,
    title: 'Тестовый PNG',
    altText: 'Однопиксельное тестовое изображение',
);

$asset = MediaRepository::fromDatabase()->findByPublicId(
    $publicId,
);

if (
    $asset === null
    || $asset->ownerOrganizationPublicId !== $root->publicId
    || $asset->mediaType !== 'image'
    || $asset->mimeType !== 'image/png'
    || $asset->sha256 !== $first->sha256
    || $asset->bytes !== strlen($png)
    || $asset->originalName !== 'unsafe-name.php'
    || $asset->visibility !== 'private'
) {
    fwrite(
        STDERR,
        "MediaUploadService зарегистрировал metadata некорректно.\n",
    );
    exit(1);
}

$configStorage = MediaBlobStorage::fromConfig();
if (!$configStorage->exists($asset->sha256)) {
    fwrite(
        STDERR,
        "Blob из MediaUploadService отсутствует в configured storage.\n",
    );
    exit(1);
}

echo "Media safe storage smoke OK\n";
