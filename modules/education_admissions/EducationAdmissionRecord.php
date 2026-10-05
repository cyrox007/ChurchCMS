<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationAdmissions;

final readonly class EducationAdmissionRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $programPublicId,
        public string $programTitle,
        public string $programOwnerOrganizationPublicId,
        public string $status,
        public string $title,
        public string $academicYear,
        public ?string $startsOn,
        public ?string $endsOn,
        public int $budgetSeats,
        public int $paidSeats,
        public string $tuitionNote,
        public string $requirements,
        public string $entranceTests,
        public string $contactNote,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
