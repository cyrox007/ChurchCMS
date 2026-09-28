<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

final readonly class Person
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $displayName,
        public ?string $firstName,
        public ?string $middleName,
        public ?string $lastName,
        public string $biographyHtml,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
