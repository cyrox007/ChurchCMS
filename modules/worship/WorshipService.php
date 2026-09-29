<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

final readonly class WorshipService
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $title,
        public string $serviceType,
        public string $startsAt,
        public ?string $endsAt,
        public ?string $locationName,
        public string $descriptionHtml,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
