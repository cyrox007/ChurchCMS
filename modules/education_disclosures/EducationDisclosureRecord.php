<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationDisclosures;

final readonly class EducationDisclosureRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $sectionKey,
        public string $title,
        public string $summary,
        public string $bodyHtml,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
