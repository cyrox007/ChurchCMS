<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final class MediaGalleryPublicService
{
    public function __construct(
        private readonly MediaGalleryRepository $galleries,
        private readonly MediaGalleryService $galleryService,
        private readonly MediaDerivativeRepository $derivatives,
        private readonly MediaPublicFileService $files,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            MediaGalleryRepository::fromDatabase(),
            MediaGalleryService::fromDatabase(),
            MediaDerivativeRepository::fromDatabase(),
            MediaPublicFileService::fromConfig(),
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function index(
        string $siteKey = 'default',
        int $limit = 24,
    ): array {
        $limit = max(1, min(50, $limit));
        $rows = [];

        foreach (
            $this->galleries->publicPublished(
                $siteKey,
                100,
            ) as $gallery
        ) {
            $projection = $this->project(
                $gallery,
                previewOnly: true,
            );

            if ($projection === null) {
                continue;
            }

            $rows[] = $projection;

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function detail(
        string $publicId,
        string $siteKey = 'default',
    ): ?array {
        $gallery = $this->galleries->findByPublicId(
            trim($publicId),
            $siteKey,
        );

        if (
            $gallery === null
            || $gallery->status !== 'published'
            || $gallery->visibility !== 'public'
        ) {
            return null;
        }

        return $this->project(
            $gallery,
            previewOnly: false,
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function project(
        MediaGallery $gallery,
        bool $previewOnly,
    ): ?array {
        $items = [];

        foreach (
            $this->galleryService->items(
                $gallery->publicId,
                $gallery->siteKey,
            ) as $asset
        ) {
            if (
                $asset->status === 'archived'
                || $asset->visibility !== 'public'
                || $asset->mediaType !== 'image'
            ) {
                continue;
            }

            $items[] = $this->imageProjection(
                $asset,
            );

            if ($previewOnly && count($items) >= 1) {
                break;
            }
        }

        if ($items === []) {
            return null;
        }

        return [
            'id' => $gallery->publicId,
            'type' => 'gallery',
            'title' => $gallery->title,
            'description' => $gallery->description,
            'organization_owner_id' =>
                $gallery->ownerOrganizationPublicId,
            'status' => $gallery->status,
            'visibility' => $gallery->visibility,
            'url' => '/galleries/'
                . rawurlencode($gallery->publicId),
            'items_count' => $previewOnly
                ? count($this->publicItems(
                    $gallery,
                ))
                : count($items),
            'items' => $previewOnly
                ? [$items[0]]
                : $items,
            'updated_at' => $gallery->updatedAt,
        ];
    }

    /**
     * @return list<MediaAsset>
     */
    private function publicItems(
        MediaGallery $gallery,
    ): array {
        return array_values(array_filter(
            $this->galleryService->items(
                $gallery->publicId,
                $gallery->siteKey,
            ),
            static fn(MediaAsset $asset): bool =>
                $asset->status !== 'archived'
                && $asset->visibility === 'public'
                && $asset->mediaType === 'image',
        ));
    }

    /**
     * @return array<string,mixed>
     */
    private function imageProjection(
        MediaAsset $asset,
    ): array {
        $originalUrl = null;
        $mediumUrl = null;
        $thumbnailUrl = null;

        $original = $this->files->resolve(
            $asset->publicId,
            $asset->sha256,
            'original',
            $asset->siteKey,
        );

        if ($original !== null) {
            $originalUrl = MediaPublicFileService::url(
                $asset->publicId,
                $asset->sha256,
                'original',
            );
        }

        foreach (
            $this->derivatives->forAsset(
                $asset->publicId,
                $asset->siteKey,
            ) as $derivative
        ) {
            if (
                !in_array(
                    $derivative->variant,
                    ['thumbnail', 'medium'],
                    true,
                )
            ) {
                continue;
            }

            $resolved = $this->files->resolve(
                $asset->publicId,
                $derivative->sha256,
                $derivative->variant,
                $asset->siteKey,
            );

            if ($resolved === null) {
                continue;
            }

            $url = MediaPublicFileService::url(
                $asset->publicId,
                $derivative->sha256,
                $derivative->variant,
            );

            if ($derivative->variant === 'medium') {
                $mediumUrl = $url;
            }

            if ($derivative->variant === 'thumbnail') {
                $thumbnailUrl = $url;
            }
        }

        return [
            'id' => $asset->publicId,
            'title' => $asset->title,
            'alt_text' => $asset->altText,
            'mime_type' => $asset->mimeType,
            'pixel_width' => $asset->pixelWidth,
            'pixel_height' => $asset->pixelHeight,
            'original_url' => $originalUrl,
            'medium_url' => $mediumUrl,
            'thumbnail_url' => $thumbnailUrl,
            'display_url' => $mediumUrl
                ?? $originalUrl
                ?? $thumbnailUrl,
            'blob_available' =>
                $originalUrl !== null
                || $mediumUrl !== null
                || $thumbnailUrl !== null,
        ];
    }
}
