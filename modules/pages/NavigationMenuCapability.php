<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

final class NavigationMenuCapability
{
    /**
     * @return list<array{label:string,url:string}>
     */
    public function primary(
        string $siteKey = 'default',
    ): array {
        return NavigationMenuService::fromDatabase()
            ->publicNavigation(
                NavigationMenuService::PRIMARY_KEY,
                $siteKey,
            );
    }
}
