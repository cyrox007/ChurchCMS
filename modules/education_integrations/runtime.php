<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeProvider;

foreach ([
    'EducationPlatformHttpClient.php',
    'NativeEducationPlatformHttpClient.php',
    'EducationPlatformEndpoint.php',
    'EducationPlatformAdapter.php',
    'EducationPlatformAdapterRegistry.php',
    'MoodleEducationAdapter.php',
    'OjsEducationAdapter.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'education_integrations';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
    }
};
