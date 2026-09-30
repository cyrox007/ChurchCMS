<?php

declare(strict_types=1);

use ChurchCMS\Core\HttpByteRange;
use ChurchCMS\Modules\Media\FederatedMediaFeedService;
use ChurchCMS\Modules\Media\MediaPublicFileService;
use ChurchCMS\Modules\Media\MediaRepository;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaUploadService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход HTTP Range',
    'parish',
);

$directory = sys_get_temp_dir()
    . '/churchcms-media-range-'
    . bin2hex(random_bytes(6));

if (
    !mkdir($directory, 0700, true)
    && !is_dir($directory)
) {
    fwrite(STDERR, "Не удалось создать временный каталог.\n");
    exit(1);
}

$wavPath = $directory . '/sample.wav';

$data = str_repeat("\x80", 4096);
$dataLength = strlen($data);
$riffSize = 36 + $dataLength;

$wav = 'RIFF'
    . pack('V', $riffSize)
    . 'WAVE'
    . 'fmt '
    . pack(
        'VvvVVvv',
        16,
        1,
        1,
        8000,
        8000,
        1,
        8,
    )
    . 'data'
    . pack('V', $dataLength)
    . $data;

file_put_contents($wavPath, $wav);

try {
    $publicId = MediaUploadService::fromConfig()
        ->importFile(
            sourcePath: $wavPath,
            originalName: 'sample.wav',
            ownerOrganizationPublicId: $root->publicId,
            title: 'Тестовое аудио',
        );

    MediaService::fromDatabase()->setVisibility(
        $publicId,
        'public',
    );

    $asset = MediaRepository::fromDatabase()
        ->findByPublicId($publicId);

    if (
        $asset === null
        || $asset->mediaType !== 'audio'
        || !in_array(
            $asset->mimeType,
            ['audio/wav', 'audio/x-wav'],
            true,
        )
    ) {
        fwrite(
            STDERR,
            "WAV не прошёл MediaUploadService как audio.\n",
        );
        exit(1);
    }

    $file = MediaPublicFileService::fromConfig()
        ->resolve(
            $asset->publicId,
            $asset->sha256,
            'original',
        );

    if (
        $file === null
        || $file->bytes !== $asset->bytes
        || $file->mimeType !== $asset->mimeType
        || !is_file($file->path)
    ) {
        fwrite(
            STDERR,
            "Public audio blob не разрешён.\n",
        );
        exit(1);
    }

    if (HttpByteRange::fromHeader(null, $file->bytes) !== null) {
        fwrite(STDERR, "Пустой Range должен означать полный файл.\n");
        exit(1);
    }

    $fixed = HttpByteRange::fromHeader(
        'bytes=0-99',
        $file->bytes,
    );
    if (
        $fixed === null
        || $fixed->start !== 0
        || $fixed->end !== 99
        || $fixed->length() !== 100
    ) {
        fwrite(STDERR, "Фиксированный byte range разобран неверно.\n");
        exit(1);
    }

    $open = HttpByteRange::fromHeader(
        'bytes=100-',
        $file->bytes,
    );
    if (
        $open === null
        || $open->start !== 100
        || $open->end !== $file->bytes - 1
        || $open->length() !== $file->bytes - 100
    ) {
        fwrite(STDERR, "Открытый byte range разобран неверно.\n");
        exit(1);
    }

    $suffix = HttpByteRange::fromHeader(
        'bytes=-128',
        $file->bytes,
    );
    if (
        $suffix === null
        || $suffix->start !== $file->bytes - 128
        || $suffix->end !== $file->bytes - 1
        || $suffix->length() !== 128
    ) {
        fwrite(STDERR, "Suffix byte range разобран неверно.\n");
        exit(1);
    }

    $clamped = HttpByteRange::fromHeader(
        'bytes=0-99999999',
        $file->bytes,
    );
    if (
        $clamped === null
        || $clamped->end !== $file->bytes - 1
    ) {
        fwrite(STDERR, "Конец Range не ограничен размером файла.\n");
        exit(1);
    }

    foreach ([
        'bytes=0-1,3-4',
        'bytes=' . $file->bytes . '-',
        'bytes=100-99',
        'bytes=-0',
        'items=0-9',
    ] as $invalid) {
        try {
            HttpByteRange::fromHeader(
                $invalid,
                $file->bytes,
            );
            fwrite(
                STDERR,
                "Некорректный Range ошибочно принят: {$invalid}\n",
            );
            exit(1);
        } catch (\InvalidArgumentException) {
        }
    }

    $feed = FederatedMediaFeedService::fromDatabase()
        ->latest(
            'default',
            20,
        );

    $local = null;
    foreach ($feed as $item) {
        if (($item['id'] ?? null) === $publicId) {
            $local = $item;
            break;
        }
    }

    if (
        !is_array($local)
        || ($local['media_type'] ?? null) !== 'audio'
        || ($local['blob_available'] ?? false) !== true
        || ($local['url'] ?? null)
            !== '/api/v1/media/'
                . rawurlencode($publicId)
    ) {
        fwrite(
            STDERR,
            "Агрегированная Media-лента не отметила public audio blob.\n",
        );
        exit(1);
    }

    echo "Media HTTP Range smoke OK\n";
} finally {
    if (is_file($wavPath)) {
        @unlink($wavPath);
    }

    @rmdir($directory);
}
