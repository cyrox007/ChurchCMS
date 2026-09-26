<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use InvalidArgumentException;

final class RouteTemplate
{
    public static function normalize(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new InvalidArgumentException('Invalid route path.');
        }

        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    public static function compile(string $path): string
    {
        $normalized = self::normalize($path);
        $quoted = preg_quote($normalized, '#');

        $pattern = preg_replace_callback(
            '/\\\{([A-Za-z_][A-Za-z0-9_]*)\\\}/',
            static fn(array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $quoted,
        );

        if (!is_string($pattern)) {
            throw new InvalidArgumentException('Unable to compile route.');
        }

        return '#^' . $pattern . '$#D';
    }
}
