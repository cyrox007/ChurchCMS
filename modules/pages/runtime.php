<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeProvider;

$moduleRoot = __DIR__;
foreach ([
    'PageStatus.php',
    'Page.php',
    'PageRepository.php',
    'PageService.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'pages';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        // Публичные/admin/API маршруты подключаются следующим vertical slice.
    }
};
