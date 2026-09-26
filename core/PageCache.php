<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class PageCache
{
    public function __construct(
        private readonly string $directory,
        private readonly int $ttlSeconds,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            CHURCHCMS_ROOT . '/storage/cache/pages',
            max(1, min(3600, (int) Config::get('performance.page_cache.ttl_seconds', 60))),
        );
    }

    public function key(Request $request): string
    {
        $version = $this->version();
        $theme = (string) Config::get('theme.active', 'default');
        $query = trim((string) $request->server('QUERY_STRING', ''));

        return hash('sha256', implode('|', [
            'v2',
            $version,
            $theme,
            $request->path(),
            $query,
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

    public static function bumpVersion(): void
    {
        $directory = CHURCHCMS_ROOT . '/storage/cache/pages';
        if (!is_dir($directory)) {
            @mkdir($directory, 0750, true);
        }

        $file = $directory . '/.version';
        $value = bin2hex(random_bytes(12));
        $temp = $file . '.tmp-' . bin2hex(random_bytes(4));

        if (file_put_contents($temp, $value, LOCK_EX) !== false) {
            @chmod($temp, 0640);
            @rename($temp, $file);
        }
    }

    private function version(): string
    {
        $file = $this->directory . '/.version';
        if (!is_file($file)) {
            return 'initial';
        }

        $value = trim((string) file_get_contents($file));
        return $value !== '' ? $value : 'initial';
    }

    private function path(string $key): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $key) !== 1) {
            throw new RuntimeException('Invalid page cache key.');
        }

        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $key
            . '.html';
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create page cache directory.');
        }
    }
}
