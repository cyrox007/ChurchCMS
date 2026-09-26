<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class RuntimeAutoloader
{
    private const PREFIXES = [
        'ChurchCMS\\Core\\' => 'core',
        'ChurchCMS\\App\\Controllers\\' => 'app/controllers',
        'ChurchCMS\\App\\Middlewares\\' => 'app/middlewares',
        'ChurchCMS\\App\\Models\\' => 'app/models',
        'ChurchCMS\\App\\Services\\' => 'app/services',
    ];

    private static bool $registered = false;

    public static function register(string $root): void
    {
        if (self::$registered) {
            return;
        }

        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false || !is_dir($resolvedRoot) || is_link($root)) {
            throw new RuntimeException('Unsafe application root.');
        }

        spl_autoload_register(static function (string $class) use ($resolvedRoot): void {
            foreach (self::PREFIXES as $prefix => $directory) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }

                $relative = substr($class, strlen($prefix));
                if ($relative === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $relative) !== 1) {
                    return;
                }

                $candidate = $resolvedRoot . DIRECTORY_SEPARATOR . $directory
                    . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

                $resolved = realpath($candidate);
                if ($resolved === false || !is_file($resolved) || is_link($candidate)) {
                    return;
                }

                $prefixPath = rtrim($resolvedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if (!str_starts_with($resolved, $prefixPath)) {
                    throw new RuntimeException('Autoload path escaped application root.');
                }

                require_once $resolved;
                return;
            }
        });

        self::$registered = true;
    }
}
