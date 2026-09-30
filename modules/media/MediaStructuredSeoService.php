<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final class MediaStructuredSeoService
{
    public function __construct(
        private readonly MediaRepository $media,
        private readonly MediaDerivativeRepository $derivatives,
        private readonly MediaPublicFileService $publicFiles,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            MediaRepository::fromDatabase(),
            MediaDerivativeRepository::fromDatabase(),
            MediaPublicFileService::fromConfig(),
        );
    }

    /**
     * @param list<string>|null $ownerPublicIds
     * @return list<array<string,mixed>>
     */
    public function availableAssets(
        ?array $ownerPublicIds,
        string $siteKey = 'default',
    ): array {
        $result = [];

        foreach (
            $this->media->adminList(
                $ownerPublicIds,
                $siteKey,
                500,
            ) as $asset
        ) {
            if (
                $asset->status === 'archived'
                || $asset->visibility !== 'public'
                || !in_array(
                    $asset->mediaType,
                    ['image', 'video'],
                    true,
                )
            ) {
                continue;
            }

            $descriptor = $this->descriptor(
                $asset->publicId,
                $siteKey,
            );

            if ($descriptor !== null) {
                $result[] = $descriptor;
            }
        }

        return $result;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function descriptor(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): ?array {
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
                ['image', 'video'],
                true,
            )
        ) {
            return null;
        }

        $variant = 'original';
        $sha256 = $asset->sha256;
        $mimeType = $asset->mimeType;
        $bytes = $asset->bytes;
        $width = $asset->pixelWidth;
        $height = $asset->pixelHeight;

        if ($asset->mediaType === 'image') {
            $medium = $this->derivatives->find(
                $asset->publicId,
                'medium',
                $siteKey,
            );

            if ($medium !== null) {
                $variant = $medium->variant;
                $sha256 = $medium->sha256;
                $mimeType = $medium->mimeType;
                $bytes = $medium->bytes;
                $width = $medium->pixelWidth;
                $height = $medium->pixelHeight;
            }
        }

        if (
            $this->publicFiles->resolve(
                $asset->publicId,
                $sha256,
                $variant,
                $siteKey,
            ) === null
        ) {
            return null;
        }

        return [
            'public_id' => $asset->publicId,
            'media_type' => $asset->mediaType,
            'mime_type' => $mimeType,
            'bytes' => $bytes,
            'title' => $asset->title
                ?? $asset->originalName,
            'alt_text' => $asset->altText,
            'width' => $width,
            'height' => $height,
            'created_at' => $asset->createdAt,
            'updated_at' => $asset->updatedAt,
            'url' => MediaPublicFileService::url(
                $asset->publicId,
                $sha256,
                $variant,
            ),
            'variant' => $variant,
        ];
    }
}
