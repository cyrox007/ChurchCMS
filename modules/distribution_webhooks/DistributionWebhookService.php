<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

use ChurchCMS\Core\DistributionEventListener;

final class DistributionWebhookService implements DistributionEventListener
{
    public function __construct(
        private readonly DistributionWebhookEndpointRepository $endpoints,
        private readonly DistributionWebhookDeliveryRepository $deliveries,
        private readonly string $siteKey = 'default',
    ) {
    }

    public static function fromDatabase(string $siteKey = 'default'): self
    {
        return new self(
            DistributionWebhookEndpointRepository::fromDatabase(),
            DistributionWebhookDeliveryRepository::fromDatabase(),
            $siteKey,
        );
    }

    public function enqueue(string $eventType, array $payload): void
    {
        foreach ($this->endpoints->activeForEvent($this->siteKey, $eventType) as $endpoint) {
            $this->deliveries->enqueue($endpoint, $eventType, $this->safePayload($payload));
        }
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function safePayload(array $payload): array
    {
        $allowed = [
            'public_id',
            'site_key',
            'type',
            'status',
            'slug',
            'title',
            'excerpt',
            'published_at',
            'updated_at',
            'canonical_url',
            'organization_owner_id',
        ];

        $result = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $result[$key] = $payload[$key];
            }
        }

        return $result;
    }
}
