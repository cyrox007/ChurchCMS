<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Media\MediaPublicFileService;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaUploadService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход public Media',
    'parish',
);

$directory = sys_get_temp_dir()
    . '/churchcms-public-media-'
    . bin2hex(random_bytes(6));

if (
    !mkdir($directory, 0700, true)
    && !is_dir($directory)
) {
    fwrite(STDERR, "Не удалось создать временный каталог.\n");
    exit(1);
}

$imagePath = $directory . '/public.png';
$privatePath = $directory . '/private.png';
$pdfPath = $directory . '/document.pdf';

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

    $color = imagecolorallocate(
        $image,
        80,
        120,
        160,
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
            'Не удалось записать тестовое изображение.'
        );
    }

    imagedestroy($image);
};

try {
    $createPng(
        $imagePath,
        1600,
        900,
    );
    $createPng(
        $privatePath,
        640,
        480,
    );
    file_put_contents(
        $pdfPath,
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n",
    );

    $upload = MediaUploadService::fromConfig();

    $imagePublicId = $upload->importFile(
        sourcePath: $imagePath,
        originalName: 'public.png',
        ownerOrganizationPublicId: $root->publicId,
    );
    $privatePublicId = $upload->importFile(
        sourcePath: $privatePath,
        originalName: 'private.png',
        ownerOrganizationPublicId: $root->publicId,
    );
    $documentPublicId = $upload->importFile(
        sourcePath: $pdfPath,
        originalName: 'document.pdf',
        ownerOrganizationPublicId: $root->publicId,
    );

    $media = MediaService::fromDatabase();
    $media->setVisibility(
        $imagePublicId,
        'public',
    );
    $media->setVisibility(
        $documentPublicId,
        'public',
    );

    $derivatives = ModuleRuntimeLoader::capability(
        'media',
        'media.derivatives',
    );

    if (
        $derivatives === null
        || !method_exists(
            $derivatives,
            'generate',
        )
    ) {
        fwrite(
            STDERR,
            "Capability media.derivatives недоступен.\n",
        );
        exit(1);
    }

    $thumbnail = $derivatives->generate(
        $imagePublicId,
        'thumbnail',
    );

    $service = MediaPublicFileService::fromConfig();

    $originalAsset = \ChurchCMS\Modules\Media\MediaRepository::fromDatabase()
        ->findByPublicId($imagePublicId);
    $documentAsset = \ChurchCMS\Modules\Media\MediaRepository::fromDatabase()
        ->findByPublicId($documentPublicId);
    $privateAsset = \ChurchCMS\Modules\Media\MediaRepository::fromDatabase()
        ->findByPublicId($privatePublicId);

    if (
        $originalAsset === null
        || $documentAsset === null
        || $privateAsset === null
    ) {
        fwrite(STDERR, "Media assets не найдены.\n");
        exit(1);
    }

    $original = $service->resolve(
        $imagePublicId,
        $originalAsset->sha256,
        'original',
    );

    if (
        $original === null
        || $original->mimeType !== 'image/png'
        || $original->bytes !== $originalAsset->bytes
        || !is_file($original->path)
    ) {
        fwrite(STDERR, "Public original image не разрешён.\n");
        exit(1);
    }

    $derived = $service->resolve(
        $imagePublicId,
        $thumbnail->sha256,
        'thumbnail',
    );

    if (
        $derived === null
        || $derived->sha256 !== $thumbnail->sha256
        || $derived->variant !== 'thumbnail'
    ) {
        fwrite(STDERR, "Public thumbnail не разрешён.\n");
        exit(1);
    }

    if (
        $service->resolve(
            $imagePublicId,
            str_repeat('0', 64),
            'original',
        ) !== null
    ) {
        fwrite(STDERR, "Неверный hash ошибочно разрешил Media blob.\n");
        exit(1);
    }

    if (
        $service->resolve(
            $privatePublicId,
            $privateAsset->sha256,
            'original',
        ) !== null
    ) {
        fwrite(STDERR, "Private Media ошибочно стал публичным.\n");
        exit(1);
    }

    $document = $service->resolve(
        $documentPublicId,
        $documentAsset->sha256,
        'original',
    );

    if (
        $document === null
        || $document->mimeType !== 'application/pdf'
    ) {
        fwrite(STDERR, "Public PDF не разрешён.\n");
        exit(1);
    }

    if (
        $service->resolve(
            $documentPublicId,
            $documentAsset->sha256,
            'thumbnail',
        ) !== null
    ) {
        fwrite(
            STDERR,
            "Derivative для document ошибочно разрешён.\n",
        );
        exit(1);
    }

    $expectedUrl = '/media/'
        . rawurlencode($imagePublicId)
        . '/'
        . $thumbnail->sha256
        . '/thumbnail';

    if (
        MediaPublicFileService::url(
            $imagePublicId,
            $thumbnail->sha256,
            'thumbnail',
        ) !== $expectedUrl
    ) {
        fwrite(STDERR, "Public Media URL сформирован неверно.\n");
        exit(1);
    }

    $feed = \ChurchCMS\Modules\Media\FederatedMediaFeedService::fromDatabase()
        ->latest(
            'default',
            20,
        );

    $feedById = [];
    foreach ($feed as $item) {
        $feedById[(string) ($item['id'] ?? '')] = $item;
    }

    foreach (
        [$imagePublicId, $documentPublicId]
        as $publicId
    ) {
        $item = $feedById[$publicId] ?? null;

        if (
            !is_array($item)
            || ($item['source']['kind'] ?? null) !== 'local'
            || ($item['blob_available'] ?? false) !== true
            || ($item['url'] ?? null)
                !== '/api/v1/media/'
                    . rawurlencode($publicId)
        ) {
            fwrite(
                STDERR,
                "Агрегированная лента не отметила локальный public blob.\n",
            );
            exit(1);
        }
    }

    if (isset($feedById[$privatePublicId])) {
        fwrite(
            STDERR,
            "Private Media ошибочно попал в агрегированную ленту.\n",
        );
        exit(1);
    }

    $router = Router::getInstance();

    if (
        $router->url(
            'public_media_file',
            [
                'publicId' => $imagePublicId,
                'sha256' => $thumbnail->sha256,
                'variant' => 'thumbnail',
            ],
        ) !== $expectedUrl
        || $router->url(
            'public_media_file_head',
            [
                'publicId' => $imagePublicId,
                'sha256' => $thumbnail->sha256,
                'variant' => 'thumbnail',
            ],
        ) !== $expectedUrl
    ) {
        fwrite(STDERR, "GET/HEAD Media routes зарегистрированы неверно.\n");
        exit(1);
    }

    echo "Media public blob smoke OK\n";
} finally {
    foreach (
        [$imagePath, $privatePath, $pdfPath]
        as $path
    ) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    @rmdir($directory);
}
