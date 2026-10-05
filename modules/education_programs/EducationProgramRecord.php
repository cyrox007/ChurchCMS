<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationPrograms;

final readonly class EducationProgramRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $title,
        public string $educationLevel,
        public string $studyForm,
        public ?int $durationMonths,
        public ?string $qualification,
        public string $admissionNote,
        public string $summary,
        public string $descriptionHtml,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
