<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final class MediaGalleryCapability
{
    public function service(): MediaGalleryService
    {
        return MediaGalleryService::fromDatabase();
    }

    /**
     * @return list<MediaAsset>
     */
    public function items(
        string $galleryPublicId,
        string $siteKey = 'default',
    ): array {
        return $this->service()->items(
            $galleryPublicId,
            $siteKey,
        );
    }
}
