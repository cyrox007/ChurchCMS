<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Modules\Media\MediaBlobStorage;
use ChurchCMS\Modules\Media\MediaDerivative;
use ChurchCMS\Modules\Media\MediaRepository;
use ChurchCMS\Modules\Media\MediaUploadService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

if (!function_exists('imagepng')) {
    fwrite(
        STDERR,
        "Для derivative smoke требуется GD.\n",
    );
    exit(1);
}

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход derivatives',
    'parish',
);

$temporaryDirectory = sys_get_temp_dir()
    . '/churchcms-derivative-source-'
    . bin2hex(random_bytes(6));

if (
    !mkdir(
        $temporaryDirectory,
        0700,
        true,
    )
    && !is_dir($temporaryDirectory)
) {
    fwrite(STDERR, "Не удалось создать временный каталог.\n");
    exit(1);
}

$createPng = static function (
    string $path,
    int $width,
    int $height,
): void {
    $image = imagecreatetruecolor(
        $width,
        $height,
    );

    if (!$image instanceof \GdImage) {
        throw new RuntimeException(
            'Не удалось создать тестовое изображение.'
        );
    }

    $background = imagecolorallocate(
        $image,
        30,
        80,
        120,
    );
    imagefilledrectangle(
        $image,
        0,
        0,
        $width,
        $height,
        $background,
    );

    if (!imagepng($image, $path, 6)) {
        imagedestroy($image);

        throw new RuntimeException(
            'Не удалось записать тестовое PNG.'
        );
    }

    imagedestroy($image);
};

$largePath = $temporaryDirectory . '/large.png';
$smallPath = $temporaryDirectory . '/small.png';

try {
    $createPng(
        $largePath,
        1600,
        900,
    );
    $createPng(
        $smallPath,
        120,
        80,
    );

    $upload = MediaUploadService::fromConfig();

    $largePublicId = $upload->importFile(
        sourcePath: $largePath,
        originalName: 'large.png',
        ownerOrganizationPublicId: $root->publicId,
    );

    $smallPublicId = $upload->importFile(
        sourcePath: $smallPath,
        originalName: 'small.png',
        ownerOrganizationPublicId: $root->publicId,
    );

    $capability = ModuleRuntimeLoader::capability(
        'media',
        'media.derivatives',
    );

    if (
        $capability === null
        || !method_exists($capability, 'generate')
        || !method_exists($capability, 'forAsset')
    ) {
        fwrite(
            STDERR,
            "Capability media.derivatives не зарегистрирован.\n",
        );
        exit(1);
    }

    $thumbnail = $capability->generate(
        $largePublicId,
        'thumbnail',
    );
    $medium = $capability->generate(
        $largePublicId,
        'medium',
    );

    if (
        !$thumbnail instanceof MediaDerivative
        || $thumbnail->pixelWidth !== 320
        || $thumbnail->pixelHeight !== 180
        || $medium->pixelWidth !== 1280
        || $medium->pixelHeight !== 720
    ) {
        fwrite(
            STDERR,
            "Большое изображение масштабировано неверно.\n",
        );
        exit(1);
    }

    $storage = MediaBlobStorage::fromConfig();

    foreach ([$thumbnail, $medium] as $derivative) {
        if (
            !$storage->exists($derivative->sha256)
            || $derivative->sha256 === ''
            || $derivative->bytes <= 0
            || $derivative->mimeType !== 'image/png'
        ) {
            fwrite(
                STDERR,
                "Derivative blob не прошёл storage-проверку.\n",
            );
            exit(1);
        }

        $path = $storage->pathForHash(
            $derivative->sha256,
        );
        $actual = hash_file('sha256', $path);

        if (
            !is_string($actual)
            || !hash_equals(
                $derivative->sha256,
                $actual,
            )
        ) {
            fwrite(
                STDERR,
                "SHA-256 derivative blob не совпал.\n",
            );
            exit(1);
        }
    }

    $smallAsset = MediaRepository::fromDatabase()
        ->findByPublicId($smallPublicId);

    if ($smallAsset === null) {
        fwrite(STDERR, "Маленький Media asset не найден.\n");
        exit(1);
    }

    $smallThumbnail = $capability->generate(
        $smallPublicId,
        'thumbnail',
    );

    if (
        $smallThumbnail->pixelWidth !== 120
        || $smallThumbnail->pixelHeight !== 80
        || $smallThumbnail->sha256 !== $smallAsset->sha256
        || $smallThumbnail->bytes !== $smallAsset->bytes
    ) {
        fwrite(
            STDERR,
            "Маленькое изображение было ошибочно увеличено или перекодировано.\n",
        );
        exit(1);
    }

    $again = $capability->generate(
        $largePublicId,
        'thumbnail',
    );

    if (
        $again->id !== $thumbnail->id
        || $again->sha256 !== $thumbnail->sha256
    ) {
        fwrite(
            STDERR,
            "Повторная генерация не обновила тот же variant slot.\n",
        );
        exit(1);
    }

    $variants = $capability->forAsset(
        $largePublicId,
    );

    if (
        count($variants) !== 2
        || array_map(
            static fn(MediaDerivative $item): string =>
                $item->variant,
            $variants,
        ) !== ['medium', 'thumbnail']
    ) {
        fwrite(
            STDERR,
            "Список derivatives сформирован неверно.\n",
        );
        exit(1);
    }

    echo "Media image derivatives smoke OK\n";
} finally {
    foreach ([$largePath, $smallPath] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    @rmdir($temporaryDirectory);
}
