<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Events;

final readonly class EventRecurrenceRule
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $title,
        public string $frequency,
        public ?int $weekday,
        public string $localTime,
        public string $timezone,
        public ?int $durationMinutes,
        public string $startsOn,
        public ?string $endsOn,
        public bool $allDay,
        public ?string $locationName,
        public string $excerpt,
        public string $descriptionHtml,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
