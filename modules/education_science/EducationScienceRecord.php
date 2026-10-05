<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationScience;

final readonly class EducationScienceRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $activityType,
        public string $title,
        public ?string $startsOn,
        public ?string $endsOn,
        public string $summary,
        public string $descriptionHtml,
        public ?string $externalUrl,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
