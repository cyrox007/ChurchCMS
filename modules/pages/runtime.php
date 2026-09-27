<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeProvider;

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
        // Routes and capabilities arrive with the next vertical Pages slice.
    }
};
