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

        $wildcardCount = preg_match_all(
            '/\{[A-Za-z_][A-Za-z0-9_]*\*\}/',
            $normalized,
        );
        if (
            $wildcardCount === false
            || $wildcardCount > 1
            || (
                $wildcardCount === 1
                && preg_match(
                    '/\/\{[A-Za-z_][A-Za-z0-9_]*\*\}$/D',
                    $normalized,
                ) !== 1
            )
        ) {
            throw new InvalidArgumentException(
                'Wildcard route parameter must be the final segment.'
            );
        }

        $quoted = preg_quote($normalized, '#');

        $pattern = preg_replace_callback(
            '/\\\{([A-Za-z_][A-Za-z0-9_]*)\\\*\\\}/',
            static fn(array $m): string =>
                '(?P<' . $m[1] . '>.+)',
            $quoted,
        );

        if (!is_string($pattern)) {
            throw new InvalidArgumentException(
                'Unable to compile wildcard route.'
            );
        }

        $pattern = preg_replace_callback(
            '/\\\{([A-Za-z_][A-Za-z0-9_]*)\\\}/',
            static fn(array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $pattern,
        );

        if (!is_string($pattern)) {
            throw new InvalidArgumentException('Unable to compile route.');
        }

        return '#^' . $pattern . '$#D';
    }
}
