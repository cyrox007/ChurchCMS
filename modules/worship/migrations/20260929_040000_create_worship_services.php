<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260929_040000_create_worship_services';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE worship_services (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'scheduled',
    title VARCHAR(255) NOT NULL,
    service_type VARCHAR(64) NOT NULL DEFAULT 'service',
    starts_at TIMESTAMP NOT NULL,
    ends_at TIMESTAMP NULL,
    location_name VARCHAR(255) NULL,
    description_html TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT worship_services_site_public_unique
        UNIQUE (site_key, public_id),
    CONSTRAINT fk_worship_services_organization_owner
        FOREIGN KEY (
            site_key,
            owner_organization_public_id
        )
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                <<<'SQL'
CREATE INDEX worship_services_schedule_idx
    ON worship_services (
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
CREATE TABLE worship_services (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'scheduled',
    title VARCHAR(255) NOT NULL,
    service_type VARCHAR(64) NOT NULL DEFAULT 'service',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    location_name VARCHAR(255) NULL,
    description_html TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY worship_services_site_public_unique (
        site_key,
        public_id
    ),
    KEY worship_services_schedule_idx (
        site_key,
        owner_organization_public_id,
        status,
        starts_at,
        id
    ),
    CONSTRAINT fk_worship_services_organization_owner
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
