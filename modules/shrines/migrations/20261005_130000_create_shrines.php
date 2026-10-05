<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20261005_130000_create_shrines';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE shrines (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    shrine_type VARCHAR(32) NOT NULL DEFAULT 'other',
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255) NULL,
    location_name VARCHAR(255) NULL,
    summary TEXT NOT NULL DEFAULT '',
    description_html TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT shrines_site_public_unique UNIQUE (site_key, public_id),
    CONSTRAINT fk_shrines_organization_owner
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                'CREATE INDEX shrines_public_idx ON shrines (site_key, status, sort_order, title, id)',
                'CREATE INDEX shrines_owner_idx ON shrines (site_key, owner_organization_public_id, status, sort_order, id)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE shrines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    shrine_type VARCHAR(32) NOT NULL DEFAULT 'other',
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255) NULL,
    location_name VARCHAR(255) NULL,
    summary TEXT NOT NULL,
    description_html TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY shrines_site_public_unique (site_key, public_id),
    KEY shrines_public_idx (site_key, status, sort_order, title, id),
    KEY shrines_owner_idx (site_key, owner_organization_public_id, status, sort_order, id),
    CONSTRAINT fk_shrines_organization_owner
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException("Неподдерживаемый драйвер миграции: {$driver}"),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $permissions = [
            'shrines.read' => 'Просмотр святынь',
            'shrines.manage' => 'Управление святынями',
        ];
        $find = $pdo->prepare('SELECT id FROM permissions WHERE permission_key = :permission_key LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO permissions (permission_key, name) VALUES (:permission_key, :name)');
        foreach ($permissions as $key => $name) {
            $find->execute(['permission_key' => $key]);
            if ($find->fetchColumn() !== false) {
                continue;
            }
            $insert->execute(['permission_key' => $key, 'name' => $name]);
        }
    }
};
