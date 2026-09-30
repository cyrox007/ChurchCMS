<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

final readonly class NavigationMenuItem
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $menuId,
        public string $itemType,
        public string $label,
        public ?string $pagePublicId,
        public ?string $routeName,
        public ?string $externalUrl,
        public int $sortOrder,
        public bool $enabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
