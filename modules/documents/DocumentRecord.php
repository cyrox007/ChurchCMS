<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

final readonly class DocumentRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $title,
        public string $documentType,
        public ?string $documentNumber,
        public ?string $issuedOn,
        public string $summary,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
