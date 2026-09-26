<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use Throwable;

final class InstallationHealthCheck
{
    private const STORAGE_PATHS = [
        'storage',
        'storage/cache',
        'storage/logs',
        'storage/sessions',
        'storage/rate-limits',
        'storage/uploads',
    ];

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly string $root,
    ) {
    }

    /**
     * @return array{ready: bool, checks: array<string, bool>}
     */
    public function check(): array
    {
        $installed = Config::get('installation.completed', false) === true;

        $checks = [
            'php' => version_compare(
                PHP_VERSION,
                (string) Config::get('app.php_min', '8.3'),
                '>='
            ),
            'installation' => $installed,
            'secret_key' => $this->secretKeyValid(),
            'storage' => $this->storageWritable(),
            'migrations' => $this->migrationsValid(),
            'database' => $installed && $this->databaseAvailable(),
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'checks' => $checks,
        ];
    }

    private function secretKeyValid(): bool
    {
        $decoded = base64_decode(
            trim((string) Config::get('security.secret_key', '')),
            true,
        );

        return is_string($decoded) && strlen($decoded) === 32;
    }

    private function storageWritable(): bool
    {
        foreach (self::STORAGE_PATHS as $relative) {
            $path = rtrim($this->root, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (!is_dir($path) || is_link($path) || !is_writable($path)) {
                return false;
            }
        }

        return true;
    }

    private function migrationsValid(): bool
    {
        try {
            (new MigrationRunner($this->database, $this->root))->validate();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function databaseAvailable(): bool
    {
        try {
            return $this->database->connection()->query('SELECT 1') !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
