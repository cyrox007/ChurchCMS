<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\SeoRenderer;
use ChurchCMS\Modules\Media\MediaBlobStorage;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaUploadService;
use ChurchCMS\Modules\Media\MediaUsageService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationRepository;
use ChurchCMS\Modules\Publications\PublicationService;
use ChurchCMS\Modules\Publications\PublicationType;
use ChurchCMS\Modules\Seo\PublicationSeoRepository;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход structured SEO',
    'parish',
);
$otherOwnerId = $organizations->create(
    name: 'Соседний отдел',
    type: 'department',
    parentPublicId: $root->publicId,
);

$directory = sys_get_temp_dir()
    . '/churchcms-structured-seo-'
    . bin2hex(random_bytes(6));

if (
    !mkdir($directory, 0700, true)
    && !is_dir($directory)
) {
    fwrite(STDERR, "Не удалось создать временный каталог.\n");
    exit(1);
}

$imagePath = $directory . '/cover.png';
$otherImagePath = $directory . '/other.png';

$createPng = static function (
    string $path,
    int $width,
    int $height,
): void {
    $image = imagecreatetruecolor($width, $height);

    if (!$image instanceof \GdImage) {
        throw new RuntimeException(
            'Не удалось создать тестовый PNG.'
        );
    }

    $color = imagecolorallocate($image, 80, 120, 160);
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
            'Не удалось записать тестовый PNG.'
        );
    }

    imagedestroy($image);
};

try {
    $createPng($imagePath, 1200, 800);
    $createPng($otherImagePath, 640, 480);

    $upload = MediaUploadService::fromConfig();
    $coverId = $upload->importFile(
        sourcePath: $imagePath,
        originalName: 'cover.png',
        ownerOrganizationPublicId: $root->publicId,
        title: 'Обложка публикации',
        altText: 'Алтарь храма',
    );
    $otherImageId = $upload->importFile(
        sourcePath: $otherImagePath,
        originalName: 'other.png',
        ownerOrganizationPublicId: $otherOwnerId,
        title: 'Чужая обложка',
    );

    $media = MediaService::fromDatabase();
    $media->setVisibility($coverId, 'public');
    $media->setVisibility($otherImageId, 'public');

    $videoBytes = "churchcms-structured-video-smoke\n";
    $videoHash = hash('sha256', $videoBytes);
    $storage = MediaBlobStorage::fromConfig();
    $videoPath = $storage->pathForHash($videoHash);

    if (
        !is_dir(dirname($videoPath))
        && !mkdir(dirname($videoPath), 0700, true)
        && !is_dir(dirname($videoPath))
    ) {
        throw new RuntimeException(
            'Не удалось создать каталог video blob.'
        );
    }

    file_put_contents($videoPath, $videoBytes);
    chmod($videoPath, 0600);

    $videoId = $media->registerMetadata(
        mediaType: 'video',
        originalName: 'service.mp4',
        mimeType: 'video/mp4',
        bytes: strlen($videoBytes),
        sha256: $videoHash,
        ownerOrganizationPublicId: $root->publicId,
        title: 'Видеозапись богослужения',
    );
    $media->setVisibility($videoId, 'public');

    $publicationId = PublicationService::fromDatabase()
        ->createDraft(
            title: 'Пасхальное богослужение </script><script>alert(1)</script>',
            slug: 'paschal-service',
            type: PublicationType::News,
            excerpt: 'Фоторепортаж и видеозапись богослужения.',
            bodyHtml: 'Текст публикации',
            authorName: 'Редакция прихода',
            ownerOrganizationPublicId: $root->publicId,
        );

    PublicationService::fromDatabase()
        ->publish($publicationId);

    $publication = PublicationRepository::fromDatabase()
        ->findByPublicId($publicationId);

    if ($publication === null) {
        fwrite(STDERR, "Публикация structured SEO не найдена.\n");
        exit(1);
    }

    $capability = ModuleRuntimeLoader::capability(
        'seo',
        'seo.publications',
    );

    if (
        $capability === null
        || !method_exists($capability, 'structuredMediaOptions')
        || !method_exists($capability, 'savePublication')
        || !method_exists($capability, 'metaForPublication')
    ) {
        fwrite(STDERR, "SEO capability не расширен structured Media.\n");
        exit(1);
    }

    $options = $capability->structuredMediaOptions(
        [$root->publicId],
    );

    $imageIds = array_column($options['images'], 'public_id');
    $videoIds = array_column($options['videos'], 'public_id');

    if (
        !in_array($coverId, $imageIds, true)
        || in_array($otherImageId, $imageIds, true)
        || !in_array($videoId, $videoIds, true)
    ) {
        fwrite(
            STDERR,
            "Structured SEO options нарушили organization scope или media type.\n",
        );
        exit(1);
    }

    $capability->savePublication(
        $publication,
        [
            'seo_title' => 'SEO Пасха',
            'seo_description' => 'Описание для поиска',
            'social_image_url' => '',
            'seo_image_media_id' => $coverId,
            'seo_video_media_id' => $videoId,
            'seo_video_thumbnail_media_id' => $coverId,
            'robots_index' => true,
            'robots_follow' => true,
        ],
        [$root->publicId],
    );

    $refs = MediaUsageService::fromDatabase()
        ->forConsumer(
            'publication-seo',
            $publication->publicId,
        );

    $slots = [];
    foreach ($refs as $ref) {
        $slots[$ref->usageKey] = $ref->mediaPublicId;
    }

    if (
        ($slots['image'] ?? null) !== $coverId
        || ($slots['video'] ?? null) !== $videoId
        || ($slots['video-thumbnail'] ?? null) !== $coverId
    ) {
        fwrite(STDERR, "Structured SEO usage slots сохранены неверно.\n");
        exit(1);
    }

    $meta = $capability->metaForPublication($publication);
    $expectedImagePart = '/media/' . rawurlencode($coverId) . '/';

    if (
        !str_contains(
            (string) ($meta['image'] ?? ''),
            $expectedImagePart,
        )
        || !is_array($meta['structured_data'] ?? null)
    ) {
        fwrite(
            STDERR,
            "Structured SEO не выбрал Media image или JSON-LD.\n",
        );
        exit(1);
    }

    $article = $meta['structured_data'][0] ?? null;
    $video = is_array($article)
        ? ($article['video'] ?? null)
        : null;

    if (
        !is_array($article)
        || ($article['@type'] ?? null) !== 'NewsArticle'
        || !is_array($article['image'] ?? null)
        || !is_array($video)
        || ($video['@type'] ?? null) !== 'VideoObject'
        || empty($video['thumbnailUrl'])
        || empty($video['uploadDate'])
        || !str_contains(
            (string) ($video['contentUrl'] ?? ''),
            '/media/' . rawurlencode($videoId) . '/',
        )
    ) {
        fwrite(
            STDERR,
            "Article/VideoObject structured data сформированы неполно.\n",
        );
        exit(1);
    }

    $rendered = SeoRenderer::render(
        $meta,
        $publication->title,
    );

    if (
        !str_contains(
            $rendered,
            '<script type="application/ld+json">'
        )
        || !str_contains($rendered, '"VideoObject"')
        || str_contains(
            $rendered,
            '</script><script>alert(1)</script>'
        )
        || !str_contains($rendered, '\\u003C/script\\u003E')
    ) {
        fwrite(
            STDERR,
            "JSON-LD рендер небезопасен или неполон.\n",
        );
        exit(1);
    }

    try {
        $capability->savePublication(
            $publication,
            [
                'seo_title' => 'Не должно сохраниться',
                'seo_video_media_id' => $videoId,
                'seo_video_thumbnail_media_id' => '',
                'robots_index' => true,
                'robots_follow' => true,
            ],
            [$root->publicId],
        );
        fwrite(
            STDERR,
            "Видео без thumbnail ошибочно сохранено.\n",
        );
        exit(1);
    } catch (\InvalidArgumentException) {
    }

    $seoForm = PublicationSeoRepository::fromDatabase()
        ->formFor($publication);

    if (($seoForm['seo_title'] ?? null) !== 'SEO Пасха') {
        fwrite(
            STDERR,
            "Неудачный structured SEO save не откатил SEO-транзакцию.\n",
        );
        exit(1);
    }

    try {
        $capability->savePublication(
            $publication,
            [
                'seo_image_media_id' => $otherImageId,
                'robots_index' => true,
                'robots_follow' => true,
            ],
            [$root->publicId],
        );
        fwrite(
            STDERR,
            "Media из чужой organization scope ошибочно сохранён.\n",
        );
        exit(1);
    } catch (\InvalidArgumentException) {
    }

    $capability->savePublication(
        $publication,
        [
            'social_image_url' =>
                'https://cdn.example.test/manual.jpg',
            'seo_image_media_id' => $coverId,
            'seo_video_media_id' => $videoId,
            'seo_video_thumbnail_media_id' => $coverId,
            'robots_index' => true,
            'robots_follow' => true,
        ],
        [$root->publicId],
    );

    $manualMeta = $capability->metaForPublication(
        $publication,
    );

    if (
        ($manualMeta['image'] ?? null)
        !== 'https://cdn.example.test/manual.jpg'
    ) {
        fwrite(
            STDERR,
            "Ручной social_image_url потерял приоритет.\n",
        );
        exit(1);
    }

    try {
        $media->archive($coverId);
        fwrite(
            STDERR,
            "Используемое structured SEO image ошибочно архивировано.\n",
        );
        exit(1);
    } catch (\InvalidArgumentException) {
    }

    echo "Structured Media SEO smoke OK\n";
} finally {
    foreach ([$imagePath, $otherImagePath] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    @rmdir($directory);
}
