<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\SecretVault;
use ChurchCMS\Modules\Media\MediaBlobStorage;
use ChurchCMS\Modules\Media\MediaResumableTransferRepository;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaUsageService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход resumable Media',
    'parish',
);

$contents = str_repeat(
    "ChurchCMS-video-chunk-0123456789\n",
    100000,
);
$bytes = strlen($contents);
$sha256 = hash('sha256', $contents);

$storage = MediaBlobStorage::fromConfig();
$blobPath = $storage->pathForHash($sha256);

if (
    !is_dir(dirname($blobPath))
    && !mkdir(dirname($blobPath), 0700, true)
    && !is_dir(dirname($blobPath))
) {
    fwrite(STDERR, "Не удалось создать каталог тестового Media blob.\n");
    exit(1);
}

file_put_contents($blobPath, $contents);
chmod($blobPath, 0600);

$media = MediaService::fromDatabase();
$videoPublicId = $media->registerMetadata(
    mediaType: 'video',
    originalName: 'resumable.mp4',
    mimeType: 'video/mp4',
    bytes: $bytes,
    sha256: $sha256,
    ownerOrganizationPublicId: $root->publicId,
    title: 'Resumable smoke video',
);
$media->setVisibility(
    $videoPublicId,
    'public',
);

$publicationPublicId = PublicationService::fromDatabase()
    ->createDraft(
        title: 'Публикация с resumable-видео',
        ownerOrganizationPublicId: $root->publicId,
    );

MediaUsageService::fromDatabase()->replaceConsumerReferences(
    'publication-seo',
    $publicationPublicId,
    [
        'video' => $videoPublicId,
    ],
);

$capability = ModuleRuntimeLoader::capability(
    'media',
    'media.resumable-upload',
);

if (
    $capability === null
    || !method_exists($capability, 'service')
    || !method_exists($capability, 'publicationVideo')
) {
    fwrite(STDERR, "Capability media.resumable-upload недоступен.\n");
    exit(1);
}

$descriptor = $capability->publicationVideo(
    $publicationPublicId,
);

if (
    !is_array($descriptor)
    || ($descriptor['public_id'] ?? null) !== $videoPublicId
    || ($descriptor['type'] ?? null) !== 'video'
    || ($descriptor['mime_type'] ?? null) !== 'video/mp4'
    || ($descriptor['bytes'] ?? null) !== $bytes
    || ($descriptor['sha256'] ?? null) !== $sha256
) {
    fwrite(STDERR, "Outbound video descriptor сформирован неверно.\n");
    exit(1);
}

$service = $capability->service();
$source = $service->source($videoPublicId);

if (
    $source->bytes !== $bytes
    || $source->sha256 !== $sha256
    || $source->path !== realpath($blobPath)
) {
    fwrite(STDERR, "MediaBinarySource сформирован неверно.\n");
    exit(1);
}

$session = 'https://upload.example.test/session/secret-token';
$transfer = $service->begin(
    $source,
    'youtube',
    'social-post-1',
    $session,
);

if (
    $transfer->status !== 'active'
    || $transfer->uploadedBytes !== 0
    || $transfer->totalBytes !== $bytes
    || $service->session($transfer) !== $session
) {
    fwrite(STDERR, "Resumable transfer создан неверно.\n");
    exit(1);
}

$stored = MediaResumableTransferRepository::fromDatabase()
    ->findByPublicId($transfer->publicId);

if (
    $stored === null
    || $stored->sessionEncrypted === $session
    || !str_starts_with($stored->sessionEncrypted, 'enc:v1:')
) {
    fwrite(STDERR, "Remote resumable session хранится небезопасно.\n");
    exit(1);
}

$first = $service->readNext(
    $transfer,
    1048576,
);

if (
    $first->offset !== 0
    || $first->bytes() !== 1048576
    || $first->nextOffset !== 1048576
    || substr($contents, 0, 1048576) !== $first->data
) {
    fwrite(STDERR, "Первый Media chunk прочитан неверно.\n");
    exit(1);
}

$advanced = $service->advance(
    $transfer,
    $first,
);

try {
    $service->advance(
        $transfer,
        $first,
    );
    fwrite(
        STDERR,
        "Старый offset ошибочно продвинул transfer повторно.\n",
    );
    exit(1);
} catch (RuntimeException) {
}

$failed = $service->fail(
    $advanced,
    "временная ошибка\nпровайдера",
);

if (
    $failed->status !== 'failed'
    || $failed->uploadedBytes !== $first->nextOffset
    || $failed->lastError !== 'временная ошибка провайдера'
) {
    fwrite(STDERR, "Failed resumable state сохранён неверно.\n");
    exit(1);
}

$transfer = $service->begin(
    $source,
    'youtube',
    'social-post-1',
    'https://upload.example.test/session/restarted',
);

if (
    $transfer->uploadedBytes !== 0
    || $transfer->status !== 'active'
) {
    fwrite(STDERR, "Restart resumable transfer не сбросил offset.\n");
    exit(1);
}

while ($transfer->uploadedBytes < $transfer->totalBytes) {
    $chunk = $service->readNext(
        $transfer,
        786432,
    );

    if ($chunk->bytes() <= 0) {
        fwrite(STDERR, "Pipeline вернул пустой chunk до EOF.\n");
        exit(1);
    }

    $transfer = $service->advance(
        $transfer,
        $chunk,
    );
}

if ($transfer->uploadedBytes !== $bytes) {
    fwrite(STDERR, "Pipeline не дошёл до полного размера файла.\n");
    exit(1);
}

$transfer = $service->complete($transfer);

if (
    $transfer->status !== 'completed'
    || $transfer->uploadedBytes !== $bytes
) {
    fwrite(STDERR, "Resumable transfer не завершён.\n");
    exit(1);
}

try {
    $service->begin(
        $source,
        'youtube',
        'social-post-1',
        'https://upload.example.test/session/third',
    );
    fwrite(
        STDERR,
        "Завершённый transfer ошибочно перезапущен тем же target key.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Media resumable binary pipeline smoke OK\n";
