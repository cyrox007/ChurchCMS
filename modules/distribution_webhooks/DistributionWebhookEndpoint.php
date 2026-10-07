<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

final readonly class DistributionWebhookEndpoint
{
    /** @param list<string> $eventTypes */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $name,
        public string $endpointUrl,
        public string $secretEncrypted,
        public array $eventTypes,
        public bool $active,
    ) {
    }

    public function accepts(string $eventType): bool
    {
        return in_array($eventType, $this->eventTypes, true);
    }
}
