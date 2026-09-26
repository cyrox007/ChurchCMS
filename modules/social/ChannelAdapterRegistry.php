<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use RuntimeException;

final class ChannelAdapterRegistry
{
    /** @var array<string,ChannelAdapter> */
    private static array $adapters = [];

    public static function register(ChannelAdapter $adapter): void
    {
        $id = SocialProvider::normalize($adapter->providerId());

        if (isset(self::$adapters[$id])) {
            throw new RuntimeException("External channel adapter already registered: {$id}");
        }

        self::$adapters[$id] = $adapter;
    }

    public static function get(string $providerId): ?ChannelAdapter
    {
        return self::$adapters[SocialProvider::normalize($providerId)] ?? null;
    }

    /** @return array<string,ChannelAdapter> */
    public static function all(): array
    {
        return self::$adapters;
    }

    public static function supports(string $providerId, string $capability): bool
    {
        $adapter = self::get($providerId);

        return $adapter !== null
            && in_array($capability, $adapter->capabilities(), true);
    }
}
