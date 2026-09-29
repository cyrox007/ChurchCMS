<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeProvider;

$moduleRoot = __DIR__;
foreach ([
    'DocumentRecord.php',
    'DocumentRepository.php',
    'DocumentService.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'documents';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
    }
};
