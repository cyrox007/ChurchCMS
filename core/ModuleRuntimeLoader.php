<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class ModuleRuntimeLoader
{
    /** @var array<string,ModuleRuntimeProvider> */
    private static array $providers = [];

    public static function boot(ModuleRegistry $registry): void
    {
        foreach ($registry->all() as $manifest) {
            $entrypoint = $manifest->runtimeEntrypoint();
            if ($entrypoint === null) {
                continue;
            }

            $moduleRoot = realpath(dirname($manifest->manifestPath()));
            if ($moduleRoot === false || !is_dir($moduleRoot)) {
                throw new RuntimeException('Invalid module root: ' . $manifest->id());
            }

            $candidate = $moduleRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entrypoint);
            $resolved = realpath($candidate);
            if ($resolved === false || !is_file($resolved) || is_link($candidate)) {
                throw new RuntimeException('Invalid module runtime: ' . $manifest->id());
            }

            $prefix = rtrim($moduleRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!str_starts_with($resolved, $prefix)) {
                throw new RuntimeException('Module runtime escaped module root: ' . $manifest->id());
            }

            $provider = require $resolved;
            if (!$provider instanceof ModuleRuntimeProvider) {
                throw new RuntimeException('Module runtime must return ModuleRuntimeProvider.');
            }

            if ($provider->moduleId() !== $manifest->id()) {
                throw new RuntimeException('Module provider id mismatch.');
            }

            $provider->boot();
            self::$providers[$manifest->id()] = $provider;
        }
    }

    public static function provider(string $moduleId): ?ModuleRuntimeProvider
    {
        return self::$providers[$moduleId] ?? null;
    }

    public static function capability(string $moduleId, string $capability): ?object
    {
        $provider = self::provider($moduleId);
        if ($provider === null) {
            return null;
        }

        $capabilities = $provider->capabilities();
        $value = $capabilities[$capability] ?? null;

        return is_object($value) ? $value : null;
    }
}
