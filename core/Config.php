<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class Config
{
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || is_link($file)) {
            throw new RuntimeException('Configuration file is missing or unsafe.');
        }

        $values = require $file;
        if (!is_array($values)) {
            throw new RuntimeException('Configuration must return an array.');
        }

        self::$values = $values;

        $local = dirname($file) . '/local.php';
        if (is_file($local) && !is_link($local)) {
            $override = require $local;
            if (!is_array($override)) {
                throw new RuntimeException('Local configuration must return an array.');
            }
            self::$values = array_replace_recursive(self::$values, $override);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $cursor = self::$values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }
}
