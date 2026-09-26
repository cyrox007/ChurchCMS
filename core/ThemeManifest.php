<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class ThemeManifest
{
    private function __construct(
        private readonly string $path,
        private readonly array $data,
    ) {
    }

    public static function load(string $path): self
    {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Theme manifest is missing or unsafe.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read theme manifest.');
        }

        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid theme manifest.');
        }

        $id = $data['id'] ?? '';
        if (!is_string($id) || preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $id) !== 1) {
            throw new RuntimeException('Invalid theme id.');
        }

        $parent = $data['parent'] ?? null;
        if ($parent !== null && (!is_string($parent) || preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $parent) !== 1)) {
            throw new RuntimeException("Invalid parent theme for {$id}.");
        }

        $templates = $data['templates'] ?? [];
        if (!is_array($templates)) {
            throw new RuntimeException("Theme {$id} templates must be an object.");
        }

        foreach ($templates as $key => $template) {
            if (
                !is_string($key)
                || preg_match('/^[a-z][a-z0-9_.-]{1,127}$/D', $key) !== 1
                || !is_string($template)
                || !self::isSafeRelativePath($template)
            ) {
                throw new RuntimeException("Theme {$id} contains an invalid template mapping.");
            }
        }

        return new self($path, $data);
    }

    public function id(): string
    {
        return (string) $this->data['id'];
    }

    public function name(): string
    {
        return (string) ($this->data['name'] ?? $this->id());
    }

    public function version(): string
    {
        return (string) ($this->data['version'] ?? '0.0.0');
    }

    public function parent(): ?string
    {
        $parent = $this->data['parent'] ?? null;
        return is_string($parent) && $parent !== '' ? $parent : null;
    }

    public function template(string $key): ?string
    {
        $value = $this->data['templates'][$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /** @return list<string> */
    public function profiles(): array
    {
        $profiles = $this->data['profiles'] ?? [];
        return is_array($profiles) ? array_values(array_filter($profiles, 'is_string')) : [];
    }

    public function manifestPath(): string
    {
        return $this->path;
    }

    public function root(): string
    {
        $root = realpath(dirname($this->path));
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Theme root is unavailable.');
        }
        return $root;
    }

    private static function isSafeRelativePath(string $path): bool
    {
        return $path !== ''
            && !str_starts_with($path, '/')
            && !str_contains($path, '..')
            && !str_contains($path, '\\')
            && preg_match('/^[A-Za-z0-9_\/.\-]+$/D', $path) === 1;
    }
}
