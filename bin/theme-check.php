#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\RuntimeAutoloader;
use ChurchCMS\Core\ThemeRegistry;
use Throwable;

$root = dirname(__DIR__);

require_once $root . '/core/RuntimeAutoloader.php';
RuntimeAutoloader::register($root);

try {
    $registry = ThemeRegistry::boot($root . '/themes');
    $filter = $argv[1] ?? null;
    $checked = 0;

    foreach ($registry->all() as $id => $theme) {
        if (is_string($filter) && $filter !== '' && $filter !== $id) {
            continue;
        }

        $checked++;
        echo "[theme] {$id} ({$theme->version()})\n";

        $chain = array_map(
            static fn($item): string => $item->id(),
            $registry->inheritanceChain($id),
        );
        echo '  inheritance: ' . implode(' -> ', $chain) . "\n";

        foreach ($theme->templates() as $logical => $relative) {
            $candidate = $theme->root() . '/templates/' . $relative . '.php';
            $resolved = realpath($candidate);
            $templatesRoot = realpath($theme->root() . '/templates');

            if (
                $resolved === false
                || $templatesRoot === false
                || !is_file($resolved)
                || !str_starts_with(
                    $resolved,
                    rtrim($templatesRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                )
            ) {
                throw new RuntimeException(
                    "Theme {$id}: template {$logical} points to missing/unsafe file {$relative}.php"
                );
            }

            echo "  ok: {$logical} -> {$relative}.php\n";
        }
    }

    if ($checked === 0) {
        fwrite(STDERR, "No matching theme found.\n");
        exit(2);
    }

    echo "Theme validation passed.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Theme validation failed: {$e->getMessage()}\n");
    exit(1);
}
