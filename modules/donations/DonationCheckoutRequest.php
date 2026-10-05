<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Donations;

final readonly class DonationCheckoutRequest
{
    public function __construct(
        public string $siteKey,
        public string $organizationPublicId,
        public int $amountMinor,
        public string $currency,
        public string $purpose,
        public string $returnPath,
    ) {
    }
}
