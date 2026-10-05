<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Donations;

final class DonationCapability
{
    /** @return list<array{id:string,label:string}> */
    public function providers(): array
    {
        return DonationProviderRegistry::available();
    }

    public function createCheckout(
        string $providerId,
        string $organizationPublicId,
        int $amountMinor,
        string $currency,
        string $purpose,
        string $returnPath,
        string $siteKey = 'default',
    ): DonationCheckout {
        return DonationService::fromDatabase()->createCheckout(
            providerId: $providerId,
            organizationPublicId: $organizationPublicId,
            amountMinor: $amountMinor,
            currency: $currency,
            purpose: $purpose,
            returnPath: $returnPath,
            siteKey: $siteKey,
        );
    }
}
