<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\PrayerRequests;

final class PrayerRequestCapability
{
    /** @return list<array{id:string,label:string,services:list<array{code:string,label:string}>}> */
    public function providers(): array
    {
        return PrayerRequestProviderRegistry::available();
    }

    /** @param list<string> $names */
    public function submit(
        string $providerId,
        string $organizationPublicId,
        string $serviceCode,
        array $names,
        ?string $comment,
        string $returnPath,
        string $siteKey = 'default',
    ): PrayerRequestReceipt {
        return PrayerRequestService::fromDatabase()->submit(
            providerId: $providerId,
            organizationPublicId: $organizationPublicId,
            serviceCode: $serviceCode,
            names: $names,
            comment: $comment,
            returnPath: $returnPath,
            siteKey: $siteKey,
        );
    }
}
