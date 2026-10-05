<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Modules\Donations\DonationCapability;

foreach ([
    'DonationCheckoutRequest.php',
    'DonationCheckout.php',
    'DonationProvider.php',
    'DonationProviderRegistry.php',
    'DonationService.php',
    'DonationCapability.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'donations';
    }

    public function capabilities(): array
    {
        return [
            'donations.checkout' => new DonationCapability(),
        ];
    }

    public function boot(): void
    {
    }
};
