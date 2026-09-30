<?php

declare(strict_types=1);

use ChurchCMS\Modules\Documents\DocumentMediaService;
use ChurchCMS\Modules\Documents\DocumentService;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaUploadService;
use ChurchCMS\Modules\Media\MediaUsageService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use InvalidArgumentException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход Document Media',
    'parish',
);

$documentPublicId = DocumentService::fromDatabase()
    ->createDraft(
        title: 'Тестовый документ',
        ownerOrganizationPublicId: $root->publicId,
    );

$directory = sys_get_temp_dir()
    . '/churchcms-document-media-'
    . bin2hex(random_bytes(6));

if (
    !mkdir($directory, 0700, true)
    && !is_dir($directory)
) {
    fwrite(STDERR, "Не удалось создать временный каталог.\n");
    exit(1);
}

$pdfPath = $directory . '/document.pdf';
$imagePath = $directory . '/image.png';

file_put_contents(
    $pdfPath,
    "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n",
);

$image = imagecreatetruecolor(80, 60);
if (!$image instanceof \GdImage) {
    fwrite(STDERR, "Не удалось создать тестовое изображение.\n");
    exit(1);
}
imagepng($image, $imagePath);
imagedestroy($image);

try {
    $upload = MediaUploadService::fromConfig();

    $pdfPublicId = $upload->importFile(
        sourcePath: $pdfPath,
        originalName: 'document.pdf',
        ownerOrganizationPublicId: $root->publicId,
    );

    $imagePublicId = $upload->importFile(
        sourcePath: $imagePath,
        originalName: 'image.png',
        ownerOrganizationPublicId: $root->publicId,
    );

    $links = DocumentMediaService::fromDatabase();
    $links->attachFile(
        $documentPublicId,
        $pdfPublicId,
    );

    if (
        $links->fileMediaPublicId($documentPublicId)
        !== $pdfPublicId
    ) {
        fwrite(STDERR, "PDF не прикрепился к документу.\n");
        exit(1);
    }

    $references = MediaUsageService::fromDatabase()
        ->forConsumer(
            'document',
            $documentPublicId,
        );

    if (
        count($references) !== 1
        || $references[0]->usageKey !== 'file'
        || $references[0]->mediaPublicId !== $pdfPublicId
    ) {
        fwrite(STDERR, "Usage reference документа сформирован неверно.\n");
        exit(1);
    }

    try {
        $links->attachFile(
            $documentPublicId,
            $imagePublicId,
        );
        fwrite(
            STDERR,
            "Изображение ошибочно разрешено как файл документа.\n",
        );
        exit(1);
    } catch (InvalidArgumentException) {
    }

    if (
        $links->fileMediaPublicId($documentPublicId)
        !== $pdfPublicId
    ) {
        fwrite(
            STDERR,
            "Неудачная замена потеряла существующий PDF.\n",
        );
        exit(1);
    }

    try {
        MediaService::fromDatabase()->archive(
            $pdfPublicId,
        );
        fwrite(
            STDERR,
            "Используемый PDF ошибочно архивирован.\n",
        );
        exit(1);
    } catch (InvalidArgumentException) {
    }

    $links->detachFile($documentPublicId);

    if (
        $links->fileMediaPublicId($documentPublicId)
        !== null
    ) {
        fwrite(STDERR, "Detach не снял ссылку документа.\n");
        exit(1);
    }

    MediaService::fromDatabase()->archive(
        $pdfPublicId,
    );

    try {
        $links->attachFile(
            $documentPublicId,
            $pdfPublicId,
        );
        fwrite(
            STDERR,
            "Архивный PDF ошибочно прикреплён повторно.\n",
        );
        exit(1);
    } catch (InvalidArgumentException) {
    }

    echo "Documents Media file link smoke OK\n";
} finally {
    foreach ([$pdfPath, $imagePath] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    @rmdir($directory);
}
