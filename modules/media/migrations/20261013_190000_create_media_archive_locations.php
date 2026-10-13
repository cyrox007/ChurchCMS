<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20261013_190000_create_media_archive_locations';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $archiveSql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE media_archive_locations (
    id BIGSERIAL PRIMARY KEY,
    sha256 CHAR(64) NOT NULL UNIQUE,
    backend VARCHAR(32) NOT NULL,
    object_key VARCHAR(512) NOT NULL,
    bytes BIGINT NOT NULL,
    state VARCHAR(32) NOT NULL DEFAULT 'ready',
    verified_at TIMESTAMP NULL,
    archived_at TIMESTAMP NOT NULL,
    last_restored_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT media_archive_backend_key_unique UNIQUE (backend, object_key)
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE media_archive_locations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sha256 CHAR(64) NOT NULL UNIQUE,
    backend VARCHAR(32) NOT NULL,
    object_key VARCHAR(512) NOT NULL,
    bytes BIGINT UNSIGNED NOT NULL,
    state VARCHAR(32) NOT NULL DEFAULT 'ready',
    verified_at DATETIME NULL,
    archived_at DATETIME NOT NULL,
    last_restored_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY media_archive_backend_key_unique (backend, object_key),
    KEY media_archive_state_idx (state, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $snapshotSql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE media_storage_snapshots (
    id BIGSERIAL PRIMARY KEY,
    local_bytes BIGINT NOT NULL,
    logical_bytes BIGINT NOT NULL,
    unique_blob_bytes BIGINT NOT NULL,
    derivative_bytes BIGINT NOT NULL,
    disk_total_bytes BIGINT NULL,
    disk_free_bytes BIGINT NULL,
    captured_at TIMESTAMP NOT NULL
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE media_storage_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    local_bytes BIGINT UNSIGNED NOT NULL,
    logical_bytes BIGINT UNSIGNED NOT NULL,
    unique_blob_bytes BIGINT UNSIGNED NOT NULL,
    derivative_bytes BIGINT UNSIGNED NOT NULL,
    disk_total_bytes BIGINT UNSIGNED NULL,
    disk_free_bytes BIGINT UNSIGNED NULL,
    captured_at DATETIME NOT NULL,
    KEY media_storage_snapshots_captured_idx (captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $pdo->exec($archiveSql);
        $pdo->exec($snapshotSql);

        if ($driver === 'pgsql') {
            $pdo->exec(
                'CREATE INDEX media_archive_state_idx
                 ON media_archive_locations (state, updated_at)'
            );
            $pdo->exec(
                'CREATE INDEX media_storage_snapshots_captured_idx
                 ON media_storage_snapshots (captured_at)'
            );
        }
    }

    public function down(\PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE media_storage_snapshots');
        $pdo->exec('DROP TABLE media_archive_locations');
    }
};
