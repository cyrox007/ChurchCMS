<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use Throwable;

final class DistributionEventRegistry
{
    /** @var list<DistributionEventListener> */
    private static array $listeners = [];

    public static function register(DistributionEventListener $listener): void
    {
        self::$listeners[] = $listener;
    }

    /** @param array<string,mixed> $payload */
    public static function dispatch(string $eventType, array $payload): void
    {
        foreach (self::$listeners as $listener) {
            try {
                $listener->enqueue($eventType, $payload);
            } catch (Throwable) {
                // Внешнее распространение не должно блокировать локальную публикацию.
            }
        }
    }
}
