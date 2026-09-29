<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeProvider;

$moduleRoot = __DIR__;
foreach ([
    'Event.php',
    'EventRepository.php',
    'EventService.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'events';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
    }
};
