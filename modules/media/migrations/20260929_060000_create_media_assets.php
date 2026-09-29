<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260929_060000_create_media_assets';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE media_assets (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'registered',
    media_type VARCHAR(64) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(255) NOT NULL,
    bytes BIGINT NOT NULL,
    sha256 CHAR(64) NOT NULL,
    title VARCHAR(255) NULL,
    alt_text VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT media_assets_site_public_unique
        UNIQUE (site_key, public_id),
    CONSTRAINT fk_media_assets_organization_owner
        FOREIGN KEY (
            site_key,
            owner_organization_public_id
        )
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                <<<'SQL'
CREATE INDEX media_assets_owner_idx
    ON media_assets (
        site_key,
        owner_organization_public_id,
        status,
        created_at,
        id
    )
SQL,
                <<<'SQL'
CREATE INDEX media_assets_checksum_idx
    ON media_assets (
        site_key,
        sha256,
        id
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE media_assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'registered',
    media_type VARCHAR(64) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(255) NOT NULL,
    bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    title VARCHAR(255) NULL,
    alt_text VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY media_assets_site_public_unique (
        site_key,
        public_id
    ),
    KEY media_assets_owner_idx (
        site_key,
        owner_organization_public_id,
        status,
        created_at,
        id
    ),
    KEY media_assets_checksum_idx (
        site_key,
        sha256,
        id
    ),
    CONSTRAINT fk_media_assets_organization_owner
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
