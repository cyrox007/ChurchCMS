<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\SundaySchool;

final readonly class SundaySchoolRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $title,
        public ?string $leaderName,
        public ?string $locationName,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $ageInfo,
        public string $summary,
        public string $descriptionHtml,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
