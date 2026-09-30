<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\Router;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Media\MediaGalleryPublicService;
use ChurchCMS\Modules\Media\MediaGalleryService;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaUploadService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход public gallery',
    'parish',
);

$directory = sys_get_temp_dir()
    . '/churchcms-public-gallery-'
    . bin2hex(random_bytes(6));

if (
    !mkdir($directory, 0700, true)
    && !is_dir($directory)
) {
    fwrite(STDERR, "Не удалось создать временный каталог.\n");
    exit(1);
}

$publicPath = $directory . '/public.png';
$privatePath = $directory . '/private.png';

$createPng = static function (
    string $path,
    int $width,
    int $height,
    int $r,
    int $g,
    int $b,
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

    $color = imagecolorallocate(
        $image,
        $r,
        $g,
        $b,
    );
    imagefilledrectangle(
        $image,
        0,
        0,
        $width,
        $height,
        $color,
    );

    if (!imagepng($image, $path, 6)) {
        imagedestroy($image);

        throw new RuntimeException(
            'Не удалось записать тестовое PNG.'
        );
    }

    imagedestroy($image);
};

try {
    $createPng(
        $publicPath,
        1600,
        900,
        30,
        80,
        120,
    );
    $createPng(
        $privatePath,
        800,
        600,
        120,
        60,
        40,
    );

    $upload = MediaUploadService::fromConfig();

    $publicImage = $upload->importFile(
        sourcePath: $publicPath,
        originalName: 'public.png',
        ownerOrganizationPublicId: $root->publicId,
        title: 'Публичное изображение',
        altText: 'Публичный alt',
    );
    $privateImage = $upload->importFile(
        sourcePath: $privatePath,
        originalName: 'private.png',
        ownerOrganizationPublicId: $root->publicId,
        title: 'Скрытое изображение',
        altText: 'Скрытый alt',
    );

    MediaService::fromDatabase()->setVisibility(
        $publicImage,
        'public',
    );

    $derivatives = ModuleRuntimeLoader::capability(
        'media',
        'media.derivatives',
    );

    if (
        $derivatives === null
        || !method_exists($derivatives, 'generate')
    ) {
        fwrite(
            STDERR,
            "Capability media.derivatives недоступен.\n",
        );
        exit(1);
    }

    $thumbnail = $derivatives->generate(
        $publicImage,
        'thumbnail',
    );
    $medium = $derivatives->generate(
        $publicImage,
        'medium',
    );

    $galleryService = MediaGalleryService::fromDatabase();
    $galleryId = $galleryService->createDraft(
        title: 'Публичная тестовая галерея',
        ownerOrganizationPublicId: $root->publicId,
        description: 'Описание публичной галереи.',
    );
    $galleryService->replaceItems(
        $galleryId,
        [
            $publicImage,
            $privateImage,
        ],
    );
    $galleryService->publish($galleryId);
    $galleryService->setVisibility(
        $galleryId,
        'public',
    );

    $public = MediaGalleryPublicService::fromConfig();
    $detail = $public->detail($galleryId);

    if (
        !is_array($detail)
        || ($detail['id'] ?? null) !== $galleryId
        || ($detail['title'] ?? null)
            !== 'Публичная тестовая галерея'
        || ($detail['items_count'] ?? null) !== 1
        || count($detail['items'] ?? []) !== 1
    ) {
        fwrite(
            STDERR,
            "Публичная projection галереи сформирована неверно.\n",
        );
        exit(1);
    }

    $item = $detail['items'][0];

    if (
        ($item['id'] ?? null) !== $publicImage
        || ($item['title'] ?? null)
            !== 'Публичное изображение'
        || ($item['alt_text'] ?? null)
            !== 'Публичный alt'
        || ($item['thumbnail_url'] ?? null)
            !== '/media/'
                . rawurlencode($publicImage)
                . '/'
                . $thumbnail->sha256
                . '/thumbnail'
        || ($item['medium_url'] ?? null)
            !== '/media/'
                . rawurlencode($publicImage)
                . '/'
                . $medium->sha256
                . '/medium'
        || ($item['blob_available'] ?? false) !== true
    ) {
        fwrite(
            STDERR,
            "Public image projection галереи неверна.\n",
        );
        exit(1);
    }

    $serialized = json_encode(
        $detail,
        JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES,
    );

    if (
        str_contains($serialized, $privateImage)
        || str_contains(
            $serialized,
            'Скрытое изображение',
        )
    ) {
        fwrite(
            STDERR,
            "Private Media утёк в публичную gallery projection.\n",
        );
        exit(1);
    }

    $index = $public->index(
        'default',
        10,
    );

    if (
        count($index) !== 1
        || ($index[0]['id'] ?? null) !== $galleryId
        || count($index[0]['items'] ?? []) !== 1
    ) {
        fwrite(
            STDERR,
            "Публичный список галерей сформирован неверно.\n",
        );
        exit(1);
    }

    $emptyGallery = $galleryService->createDraft(
        title: 'Галерея только с private',
        ownerOrganizationPublicId: $root->publicId,
    );
    $galleryService->replaceItems(
        $emptyGallery,
        [$privateImage],
    );
    $galleryService->publish($emptyGallery);
    $galleryService->setVisibility(
        $emptyGallery,
        'public',
    );

    if (
        $public->detail($emptyGallery) !== null
        || count($public->index('default', 10)) !== 1
    ) {
        fwrite(
            STDERR,
            "Галерея без public Media ошибочно стала публичной.\n",
        );
        exit(1);
    }

    $renderer = ThemeRenderer::fromConfig();

    $indexHtml = $renderer->capture(
        'gallery.index',
        [
            'heading' => 'Галереи',
            'galleries' => $index,
        ],
    );
    $detailHtml = $renderer->capture(
        'gallery.show',
        [
            'gallery' => $detail,
        ],
    );

    foreach ([
        'Публичная тестовая галерея',
        'Публичное изображение',
        'Публичный alt',
    ] as $expected) {
        if (
            !str_contains($indexHtml . $detailHtml, $expected)
        ) {
            fwrite(
                STDERR,
                "Public gallery template не содержит: {$expected}\n",
            );
            exit(1);
        }
    }

    if (
        str_contains($indexHtml . $detailHtml, 'Скрытое изображение')
        || str_contains($indexHtml . $detailHtml, $privateImage)
    ) {
        fwrite(
            STDERR,
            "Private Media утёк в публичный HTML галереи.\n",
        );
        exit(1);
    }

    $router = Router::getInstance();

    $routes = [
        'gallery_index' => '/galleries',
        'gallery_show' => '/galleries/'
            . rawurlencode($galleryId),
        'api_v1_galleries' => '/api/v1/galleries',
        'api_v1_gallery_show' => '/api/v1/galleries/'
            . rawurlencode($galleryId),
    ];

    foreach ($routes as $name => $expected) {
        $params = str_ends_with($name, '_show')
            ? ['publicId' => $galleryId]
            : [];

        if ($router->url($name, $params) !== $expected) {
            fwrite(
                STDERR,
                "Маршрут {$name} зарегистрирован неверно.\n",
            );
            exit(1);
        }
    }

    echo "Media gallery public/API smoke OK\n";
} finally {
    foreach ([$publicPath, $privatePath] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    @rmdir($directory);
}
