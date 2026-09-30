<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_110000_create_media_galleries';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE media_galleries (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    visibility VARCHAR(32) NOT NULL DEFAULT 'private',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT media_galleries_site_public_unique
        UNIQUE (site_key, public_id),
    CONSTRAINT fk_media_galleries_owner
        FOREIGN KEY (
            site_key,
            owner_organization_public_id
        )
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE media_galleries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    visibility VARCHAR(32) NOT NULL DEFAULT 'private',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY media_galleries_site_public_unique (
        site_key,
        public_id
    ),
    KEY media_galleries_owner_idx (
        site_key,
        owner_organization_public_id,
        status,
        updated_at
    ),
    CONSTRAINT fk_media_galleries_owner
        FOREIGN KEY (
            site_key,
            owner_organization_public_id
        )
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $pdo->exec($sql);

        if ($driver === 'pgsql') {
            $pdo->exec(
                'CREATE INDEX media_galleries_owner_idx
                 ON media_galleries (
                    site_key,
                    owner_organization_public_id,
                    status,
                    updated_at
                 )'
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

        $pdo->exec('DROP TABLE media_galleries');
    }
};
