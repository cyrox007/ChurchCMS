<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

final readonly class NavigationMenu
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $menuKey,
        public string $name,
        public bool $enabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
