<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final readonly class MediaGallery
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $visibility,
        public string $title,
        public string $description,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
