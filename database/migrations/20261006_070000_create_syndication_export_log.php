<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20261006_070000_create_syndication_export_log';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE syndication_export_log (
    id BIGSERIAL PRIMARY KEY,
    target VARCHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL,
    entry_count INTEGER NOT NULL DEFAULT 0,
    body_bytes BIGINT NOT NULL DEFAULT 0,
    duration_ms INTEGER NOT NULL DEFAULT 0,
    error_code VARCHAR(64) NULL,
    created_at TIMESTAMP NOT NULL
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE syndication_export_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    target VARCHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL,
    entry_count INT UNSIGNED NOT NULL DEFAULT 0,
    body_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
    error_code VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    KEY syndication_export_log_recent_idx (created_at, id),
    KEY syndication_export_log_target_idx (target, created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $pdo->exec($sql);

        if ($driver === 'pgsql') {
            $pdo->exec(
                'CREATE INDEX syndication_export_log_recent_idx '
                . 'ON syndication_export_log (created_at DESC, id DESC)'
            );
            $pdo->exec(
                'CREATE INDEX syndication_export_log_target_idx '
                . 'ON syndication_export_log (target, created_at DESC, id DESC)'
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

        $pdo->exec('DROP TABLE syndication_export_log');
    }
};
