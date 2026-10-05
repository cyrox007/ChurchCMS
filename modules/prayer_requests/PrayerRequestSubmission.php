<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\PrayerRequests;

final readonly class PrayerRequestSubmission
{
    /** @param list<string> $names */
    public function __construct(
        public string $siteKey,
        public string $organizationPublicId,
        public string $serviceCode,
        public array $names,
        public ?string $comment,
        public string $returnPath,
    ) {
    }
}
