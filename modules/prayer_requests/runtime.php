<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Modules\PrayerRequests\PrayerRequestCapability;

foreach ([
    'PrayerRequestSubmission.php',
    'PrayerRequestReceipt.php',
    'PrayerRequestProvider.php',
    'PrayerRequestProviderRegistry.php',
    'PrayerRequestService.php',
    'PrayerRequestCapability.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'prayer_requests';
    }

    public function capabilities(): array
    {
        return [
            'prayer_requests.submit' => new PrayerRequestCapability(),
        ];
    }

    public function boot(): void
    {
    }
};
