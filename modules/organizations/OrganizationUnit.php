<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

final readonly class OrganizationUnit
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public ?int $parentId,
        public string $type,
        public string $status,
        public string $slug,
        public string $path,
        public string $name,
        public ?string $shortName,
        public ?string $legalName,
        public string $descriptionHtml,
        public int $sortOrder,
    ) {
    }
}
