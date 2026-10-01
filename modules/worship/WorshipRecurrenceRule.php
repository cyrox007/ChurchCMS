<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

final readonly class WorshipRecurrenceRule
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $title,
        public string $serviceType,
        public string $frequency,
        public ?int $weekday,
        public string $localTime,
        public string $timezone,
        public ?int $durationMinutes,
        public string $startsOn,
        public ?string $endsOn,
        public ?string $locationName,
        public string $descriptionHtml,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
