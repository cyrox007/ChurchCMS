<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

final readonly class DocumentFileVersion
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public int $documentId,
        public int $versionNumber,
        public string $mediaPublicId,
        public ?string $note,
        public string $createdAt,
    ) {
    }
}
