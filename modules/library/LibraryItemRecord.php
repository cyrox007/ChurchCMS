<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Library;

final readonly class LibraryItemRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $ownerOrganizationPublicId,
        public string $status,
        public string $title,
        public ?string $authorName,
        public ?string $publisherName,
        public ?int $publicationYear,
        public ?string $isbn,
        public ?string $shelfCode,
        public ?string $availabilityNote,
        public string $summary,
        public string $descriptionHtml,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
