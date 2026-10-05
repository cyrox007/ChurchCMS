<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationStaff;

final readonly class EducationChairRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $name,
        public ?string $shortName,
        public string $descriptionHtml,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
