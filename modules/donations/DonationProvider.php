<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Donations;

interface DonationProvider
{
    public function id(): string;

    public function label(): string;

    public function createCheckout(DonationCheckoutRequest $request): DonationCheckout;
}
