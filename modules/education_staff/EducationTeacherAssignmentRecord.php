<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationStaff;

final readonly class EducationTeacherAssignmentRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $chairPublicId,
        public string $personPublicId,
        public string $personDisplayName,
        public string $status,
        public string $positionTitle,
        public ?string $academicDegree,
        public ?string $academicTitle,
        public string $disciplines,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
