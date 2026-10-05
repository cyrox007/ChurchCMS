<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Saints;

final readonly class SaintRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public ?string $saintRank,
        public string $displayName,
        public ?string $secularName,
        public ?string $commemorationText,
        public string $summary,
        public string $biographyHtml,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
