<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class ModuleRegistry
{
    /** @param array<string,ModuleManifest> $modules */
    private function __construct(private readonly array $modules)
    {
    }

    public static function boot(string $modulesRoot): self
    {
        if (!is_dir($modulesRoot) || is_link($modulesRoot)) {
            throw new RuntimeException('Modules directory is missing or unsafe.');
        }

        $modules = [];
        $entries = scandir($modulesRoot);
        if ($entries === false) {
            throw new RuntimeException('Unable to scan modules directory.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $dir = $modulesRoot . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($dir) || is_link($dir)) {
                continue;
            }

            $manifestPath = $dir . DIRECTORY_SEPARATOR . 'module.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = ModuleManifest::load($manifestPath);
            if (isset($modules[$manifest->id()])) {
                throw new RuntimeException('Duplicate module id: ' . $manifest->id());
            }

            $modules[$manifest->id()] = $manifest;
        }

        ksort($modules, SORT_STRING);
        return new self($modules);
    }

    /** @return array<string,ModuleManifest> */
    public function all(): array
    {
        return $this->modules;
    }

    public function get(string $id): ?ModuleManifest
    {
        return $this->modules[$id] ?? null;
    }
}
