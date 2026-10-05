<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Shrines;

final readonly class ShrineRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $shrineType,
        public string $title,
        public ?string $subtitle,
        public ?string $locationName,
        public string $summary,
        public string $descriptionHtml,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
