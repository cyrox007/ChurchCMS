<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

final readonly class PersonAppointment
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $personPublicId,
        public string $organizationPublicId,
        public string $title,
        public string $type,
        public string $status,
        public ?string $startedOn,
        public ?string $endedOn,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
