<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeProvider;

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'core';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        // Reserved for core module registration.
    }
};
