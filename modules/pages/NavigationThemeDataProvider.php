<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\Core\ThemeGlobalDataProvider;

final class NavigationThemeDataProvider implements
    ThemeGlobalDataProvider
{
    public function data(): array
    {
        return [
            'navigation' =>
                NavigationMenuService::fromDatabase()
                    ->publicNavigation(),
        ];
    }
}
