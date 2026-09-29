<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final readonly class MediaAsset
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $visibility,
        public string $mediaType,
        public string $originalName,
        public string $mimeType,
        public int $bytes,
        public string $sha256,
        public ?string $title,
        public ?string $altText,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
