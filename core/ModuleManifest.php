<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class ModuleManifest
{
    private function __construct(
        private readonly string $path,
        private readonly array $data,
    ) {
    }

    public static function load(string $path): self
    {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Module manifest is missing or unsafe.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read module manifest.');
        }

        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid module manifest.');
        }

        $id = $data['id'] ?? '';
        if (!is_string($id) || preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $id) !== 1) {
            throw new RuntimeException('Invalid module id.');
        }

        $entrypoint = $data['runtime'] ?? null;
        if ($entrypoint !== null && (!is_string($entrypoint) || $entrypoint === '' || str_contains($entrypoint, '..') || str_starts_with($entrypoint, '/'))) {
            throw new RuntimeException("Invalid runtime entrypoint for module {$id}.");
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

    public function runtimeEntrypoint(): ?string
    {
        $value = $this->data['runtime'] ?? null;
        return is_string($value) ? $value : null;
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        $items = $this->data['capabilities'] ?? [];
        return is_array($items) ? array_values(array_filter($items, 'is_string')) : [];
    }

    public function manifestPath(): string
    {
        return $this->path;
    }
}
