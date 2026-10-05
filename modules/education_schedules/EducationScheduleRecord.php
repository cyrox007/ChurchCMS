<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationSchedules;

final readonly class EducationScheduleRecord
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
        public string $startsAtUtc,
        public string $endsAtUtc,
        public string $timezone,
        public string $location,
        public string $note,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
