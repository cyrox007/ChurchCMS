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

        $parts = preg_split(
            '/(\{[A-Za-z_][A-Za-z0-9_]*\*?\})/',
            $normalized,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        if (!is_array($parts)) {
            throw new InvalidArgumentException(
                'Unable to compile route.'
            );
        }

        $pattern = '';
        $names = [];

        foreach ($parts as $part) {
            if (
                preg_match(
                    '/^\{([A-Za-z_][A-Za-z0-9_]*)(\*)?\}$/D',
                    $part,
                    $matches,
                ) !== 1
            ) {
                $pattern .= preg_quote($part, '#');
                continue;
            }

            $name = $matches[1];
            if (isset($names[$name])) {
                throw new InvalidArgumentException(
                    'Duplicate route parameter.'
                );
            }
            $names[$name] = true;

            $pattern .= ($matches[2] ?? '') === '*'
                ? '(?P<' . $name . '>.+)'
                : '(?P<' . $name . '>[^/]+)';
        }

        return '#^' . $pattern . '$#D';
    }
}
