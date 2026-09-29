<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260929_100000_create_worship_partner_tombstones';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE worship_partner_tombstones (
    id BIGSERIAL PRIMARY KEY,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    worship_public_id VARCHAR(36) NOT NULL,
    organization_owner_public_id VARCHAR(36) NOT NULL,
    reason VARCHAR(32) NOT NULL,
    withdrawn_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT worship_partner_tombstones_worship_unique
        UNIQUE (site_key, worship_public_id)
)
SQL,
                <<<'SQL'
CREATE INDEX worship_partner_tombstones_sync_idx
    ON worship_partner_tombstones (
        site_key,
        updated_at,
        worship_public_id
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE worship_partner_tombstones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    worship_public_id VARCHAR(36) NOT NULL,
    organization_owner_public_id VARCHAR(36) NOT NULL,
    reason VARCHAR(32) NOT NULL,
    withdrawn_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY worship_partner_tombstones_worship_unique (
        site_key,
        worship_public_id
    ),
    KEY worship_partner_tombstones_sync_idx (
        site_key,
        updated_at,
        worship_public_id
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
};
