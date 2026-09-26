<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class GeneratedOutputCache
{
    public function __construct(
        private readonly string $directory,
        private readonly int $ttlSeconds,
    ) {
    }

    public static function fromConfig(int $ttlSeconds): self
    {
        return new self(
            CHURCHCMS_ROOT . '/storage/cache/generated',
            max(1, min(86400, $ttlSeconds)),
        );
    }

    public function key(string $namespace, string $variant = ''): string
    {
        return hash('sha256', implode('|', [
            'v1',
            PageCache::versionToken(),
            $namespace,
            $variant,
        ]));
    }

    public function get(string $key): ?string
    {
        $path = $this->path($key);
        if (!is_file($path) || is_link($path)) {
            return null;
        }

        $mtime = filemtime($path);
        if ($mtime === false || $mtime + $this->ttlSeconds < time()) {
            @unlink($path);
            return null;
        }

        $body = file_get_contents($path);
        return is_string($body) ? $body : null;
    }

    public function put(string $key, string $body): void
    {
        if ($body === '') {
            return;
        }

        $this->ensureDirectory();
        $path = $this->path($key);
        $temp = $path . '.tmp-' . bin2hex(random_bytes(5));

        if (file_put_contents($temp, $body, LOCK_EX) === false) {
            return;
        }

        @chmod($temp, 0640);
        @rename($temp, $path);
    }

    private function path(string $key): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $key) !== 1) {
            throw new RuntimeException('Invalid generated-output cache key.');
        }

        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $key
            . '.cache';
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create generated-output cache directory.');
        }
    }
}
