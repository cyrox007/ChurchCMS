<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use InvalidArgumentException;

final class MediaPublicFileService
{
    public function __construct(
        private readonly MediaBlobStorage $storage,
        private readonly MediaRepository $media,
        private readonly MediaDerivativeRepository $derivatives,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            MediaBlobStorage::fromConfig(),
            MediaRepository::fromDatabase(),
            MediaDerivativeRepository::fromDatabase(),
        );
    }

    public function resolve(
        string $mediaPublicId,
        string $sha256,
        string $variant,
        string $siteKey = 'default',
    ): ?MediaPublicFile {
        $sha256 = strtolower(trim($sha256));
        $variant = trim($variant);

        if (
            preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
            || preg_match(
                '/^[a-z][a-z0-9_.-]{1,63}$/D',
                $variant,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный публичный Media URL.'
            );
        }

        $asset = $this->media->findByPublicId(
            trim($mediaPublicId),
            $siteKey,
        );

        if (
            $asset === null
            || $asset->status === 'archived'
            || $asset->visibility !== 'public'
            || !in_array(
                $asset->mediaType,
                ['image', 'document', 'audio', 'video'],
                true,
            )
        ) {
            return null;
        }

        if ($variant === 'original') {
            if (!hash_equals($asset->sha256, $sha256)) {
                return null;
            }

            $path = $this->storage->readablePath(
                $asset->sha256,
                $asset->bytes,
            );

            return $path !== null
                ? new MediaPublicFile(
                    path: $path,
                    mimeType: $asset->mimeType,
                    bytes: $asset->bytes,
                    sha256: $asset->sha256,
                    variant: 'original',
                )
                : null;
        }

        if ($asset->mediaType !== 'image') {
            return null;
        }

        $derivative = $this->derivatives->find(
            $asset->publicId,
            $variant,
            $siteKey,
        );

        if (
            $derivative === null
            || !hash_equals(
                $derivative->sha256,
                $sha256,
            )
        ) {
            return null;
        }

        $path = $this->storage->readablePath(
            $derivative->sha256,
            $derivative->bytes,
        );

        return $path !== null
            ? new MediaPublicFile(
                path: $path,
                mimeType: $derivative->mimeType,
                bytes: $derivative->bytes,
                sha256: $derivative->sha256,
                variant: $derivative->variant,
            )
            : null;
    }

    public static function url(
        string $mediaPublicId,
        string $sha256,
        string $variant,
    ): string {
        return '/media/'
            . rawurlencode($mediaPublicId)
            . '/'
            . rawurlencode($sha256)
            . '/'
            . rawurlencode($variant);
    }
}
