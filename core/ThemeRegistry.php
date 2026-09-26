<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class ThemeRegistry
{
    /** @param array<string,ThemeManifest> $themes */
    private function __construct(private readonly array $themes)
    {
    }

    public static function boot(string $themesRoot): self
    {
        if (!is_dir($themesRoot) || is_link($themesRoot)) {
            throw new RuntimeException('Themes directory is missing or unsafe.');
        }

        $themes = [];
        $entries = scandir($themesRoot);
        if ($entries === false) {
            throw new RuntimeException('Unable to scan themes directory.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $dir = $themesRoot . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($dir) || is_link($dir)) {
                continue;
            }

            $manifestPath = $dir . DIRECTORY_SEPARATOR . 'theme.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = ThemeManifest::load($manifestPath);
            if (isset($themes[$manifest->id()])) {
                throw new RuntimeException('Duplicate theme id: ' . $manifest->id());
            }

            $themes[$manifest->id()] = $manifest;
        }

        foreach ($themes as $theme) {
            $parent = $theme->parent();
            if ($parent !== null && !isset($themes[$parent])) {
                throw new RuntimeException("Theme {$theme->id()} references missing parent {$parent}.");
            }
            self::assertNoCycle($theme->id(), $themes);
        }

        ksort($themes, SORT_STRING);
        return new self($themes);
    }

    public function get(string $id): ?ThemeManifest
    {
        return $this->themes[$id] ?? null;
    }

    /** @return list<ThemeManifest> */
    public function inheritanceChain(string $id): array
    {
        $chain = [];
        $current = $this->get($id);

        while ($current !== null) {
            $chain[] = $current;
            $parent = $current->parent();
            $current = $parent !== null ? $this->get($parent) : null;
        }

        return $chain;
    }

    private static function assertNoCycle(string $id, array $themes): void
    {
        $seen = [];
        $current = $id;

        while (isset($themes[$current])) {
            if (isset($seen[$current])) {
                throw new RuntimeException("Theme inheritance cycle detected at {$current}.");
            }

            $seen[$current] = true;
            $parent = $themes[$current]->parent();
            if ($parent === null) {
                return;
            }
            $current = $parent;
        }
    }
}
