<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260929_050000_create_events';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE events (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(255) NOT NULL,
    starts_at TIMESTAMP NOT NULL,
    ends_at TIMESTAMP NULL,
    all_day SMALLINT NOT NULL DEFAULT 0,
    location_name VARCHAR(255) NULL,
    excerpt TEXT NOT NULL DEFAULT '',
    description_html TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT events_site_public_unique
        UNIQUE (site_key, public_id),
    CONSTRAINT fk_events_organization_owner
        FOREIGN KEY (
            site_key,
            owner_organization_public_id
        )
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                <<<'SQL'
CREATE INDEX events_schedule_idx
    ON events (
        site_key,
        owner_organization_public_id,
        status,
        starts_at,
        id
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(255) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    all_day TINYINT(1) NOT NULL DEFAULT 0,
    location_name VARCHAR(255) NULL,
    excerpt TEXT NOT NULL,
    description_html TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY events_site_public_unique (
        site_key,
        public_id
    ),
    KEY events_schedule_idx (
        site_key,
        owner_organization_public_id,
        status,
        starts_at,
        id
    ),
    CONSTRAINT fk_events_organization_owner
        FOREIGN KEY (
            site_key,
            owner_organization_public_id
        )
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
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
