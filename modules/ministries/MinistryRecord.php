<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Ministries;

final readonly class MinistryRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $title,
        public ?string $shortTitle,
        public ?string $leaderName,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public string $summary,
        public string $descriptionHtml,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
