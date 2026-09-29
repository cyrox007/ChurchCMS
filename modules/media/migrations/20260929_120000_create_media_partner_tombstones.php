<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260929_120000_create_media_partner_tombstones';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE media_partner_tombstones (
    id BIGSERIAL PRIMARY KEY,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    media_public_id VARCHAR(36) NOT NULL,
    organization_owner_public_id VARCHAR(36) NOT NULL,
    reason VARCHAR(32) NOT NULL,
    withdrawn_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT media_partner_tombstones_media_unique
        UNIQUE (site_key, media_public_id)
)
SQL,
                <<<'SQL'
CREATE INDEX media_partner_tombstones_sync_idx
    ON media_partner_tombstones (
        site_key,
        updated_at,
        media_public_id
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE media_partner_tombstones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    media_public_id VARCHAR(36) NOT NULL,
    organization_owner_public_id VARCHAR(36) NOT NULL,
    reason VARCHAR(32) NOT NULL,
    withdrawn_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY media_partner_tombstones_media_unique (
        site_key,
        media_public_id
    ),
    KEY media_partner_tombstones_sync_idx (
        site_key,
        updated_at,
        media_public_id
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
