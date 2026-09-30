<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use InvalidArgumentException;

final class MediaUploadService
{
    public function __construct(
        private readonly MediaBlobStorage $storage,
        private readonly MediaService $media,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            MediaBlobStorage::fromConfig(),
            MediaService::fromDatabase(),
        );
    }

    /**
     * @param array{
     *     name?:mixed,
     *     tmp_name?:mixed,
     *     error?:mixed,
     *     size?:mixed
     * } $file
     */
    public function importUploadedFile(
        array $file,
        ?string $ownerOrganizationPublicId = null,
        string $siteKey = 'default',
        ?string $title = null,
        ?string $altText = null,
    ): string {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                self::uploadErrorMessage($error),
            );
        }

        $temporary = (string) ($file['tmp_name'] ?? '');
        if (
            $temporary === ''
            || !is_uploaded_file($temporary)
        ) {
            throw new InvalidArgumentException(
                'Временный файл загрузки не прошёл проверку PHP.'
            );
        }

        return $this->importFile(
            sourcePath: $temporary,
            originalName: (string) ($file['name'] ?? ''),
            ownerOrganizationPublicId:
                $ownerOrganizationPublicId,
            siteKey: $siteKey,
            title: $title,
            altText: $altText,
        );
    }

    public function importFile(
        string $sourcePath,
        string $originalName,
        ?string $ownerOrganizationPublicId = null,
        string $siteKey = 'default',
        ?string $title = null,
        ?string $altText = null,
    ): string {
        $originalName = self::safeOriginalName(
            $originalName,
        );
        $blob = $this->storage->storeFile(
            $sourcePath,
        );

        return $this->media->registerMetadata(
            mediaType: $blob->mediaType,
            originalName: $originalName,
            mimeType: $blob->mimeType,
            bytes: $blob->bytes,
            sha256: $blob->sha256,
            ownerOrganizationPublicId:
                $ownerOrganizationPublicId,
            siteKey: $siteKey,
            title: $title,
            altText: $altText,
        );
    }

    private static function uploadErrorMessage(
        int $error,
    ): string {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE =>
                'Загружаемый Media-файл превышает разрешённый размер.',
            UPLOAD_ERR_PARTIAL =>
                'Media-файл был загружен не полностью.',
            UPLOAD_ERR_NO_FILE =>
                'Выберите Media-файл для загрузки.',
            UPLOAD_ERR_NO_TMP_DIR =>
                'На сервере недоступен временный каталог загрузки.',
            UPLOAD_ERR_CANT_WRITE =>
                'Сервер не смог записать временный Media-файл.',
            UPLOAD_ERR_EXTENSION =>
                'PHP-расширение остановило загрузку Media-файла.',
            default =>
                'Не удалось принять Media-файл.',
        };
    }

    private static function safeOriginalName(
        string $value,
    ): string {
        $value = trim(
            str_replace(
                ["\0", "\r", "\n"],
                '',
                $value,
            ),
        );
        $value = basename(
            str_replace('\\', '/', $value),
        );

        if (
            $value === ''
            || $value === '.'
            || $value === '..'
        ) {
            throw new InvalidArgumentException(
                'Некорректное имя исходного Media-файла.'
            );
        }

        return $value;
    }
}
