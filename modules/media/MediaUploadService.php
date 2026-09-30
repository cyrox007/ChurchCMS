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
