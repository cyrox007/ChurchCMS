<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use DateTimeImmutable;

final readonly class Page
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public ?int $parentId,
        public PageStatus $status,
        public string $slug,
        public string $path,
        public string $title,
        public ?string $navigationTitle,
        public string $bodyHtml,
        public int $sortOrder,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
