<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final readonly class ContentRevision
{
    /**
     * @param array<string,mixed> $snapshot
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $entityType,
        public string $entityPublicId,
        public int $revisionNumber,
        public array $snapshot,
        public string $createdAt,
    ) {
    }
}
