<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

use ChurchCMS\Core\DistributionEventListener;

final class LazyDistributionWebhookListener implements DistributionEventListener
{
    /** @param array<string,mixed> $payload */
    public function enqueue(string $eventType, array $payload): void
    {
        DistributionWebhookService::fromDatabase(
            (string) ($payload['site_key'] ?? 'default'),
        )->enqueue($eventType, $payload);
    }
}
