<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

interface DistributionEventListener
{
    /** @param array<string,mixed> $payload */
    public function enqueue(string $eventType, array $payload): void;
}
