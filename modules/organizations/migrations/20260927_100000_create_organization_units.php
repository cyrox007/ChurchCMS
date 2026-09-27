<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260927_100000_create_organization_units';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE organization_units (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    parent_id BIGINT NULL REFERENCES organization_units(id) ON DELETE RESTRICT,
    unit_type VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    slug VARCHAR(180) NOT NULL,
    path VARCHAR(700) NOT NULL,
    name VARCHAR(255) NOT NULL,
    short_name VARCHAR(255) NULL,
    legal_name VARCHAR(500) NULL,
    description_html TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT organization_units_site_path_unique UNIQUE (site_key, path)
)
SQL,
                'CREATE INDEX organization_units_tree_idx ON organization_units (site_key, parent_id, sort_order, id)',
                'CREATE INDEX organization_units_type_idx ON organization_units (site_key, unit_type, status, sort_order, id)',
                <<<'SQL'
CREATE TABLE organization_site_roots (
    site_key VARCHAR(64) PRIMARY KEY,
    organization_id BIGINT NOT NULL REFERENCES organization_units(id) ON DELETE RESTRICT,
    profile VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
)
SQL,
                'CREATE INDEX organization_site_roots_org_idx ON organization_site_roots (organization_id)',
                <<<'SQL'
CREATE TABLE organization_federation_links (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    local_organization_id BIGINT NOT NULL REFERENCES organization_units(id) ON DELETE RESTRICT,
    relation VARCHAR(16) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    remote_instance_id VARCHAR(36) NOT NULL,
    remote_organization_public_id VARCHAR(36) NOT NULL,
    remote_base_url VARCHAR(500) NOT NULL,
    remote_profile VARCHAR(64) NULL,
    remote_name VARCHAR(255) NULL,
    inbound_scopes_json TEXT NOT NULL,
    outbound_scopes_json TEXT NOT NULL,
    outbound_token_encrypted TEXT NULL,
    sync_cursor TEXT NULL,
    last_seen_at TIMESTAMP NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT organization_federation_links_site_instance_unique
        UNIQUE (site_key, remote_instance_id)
)
SQL,
                'CREATE INDEX organization_federation_links_relation_idx ON organization_federation_links (site_key, relation, status, id)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE organization_units (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    parent_id BIGINT UNSIGNED NULL,
    unit_type VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    slug VARCHAR(180) NOT NULL,
    path VARCHAR(700) NOT NULL,
    name VARCHAR(255) NOT NULL,
    short_name VARCHAR(255) NULL,
    legal_name VARCHAR(500) NULL,
    description_html TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY organization_units_site_path_unique (site_key, path),
    KEY organization_units_tree_idx (site_key, parent_id, sort_order, id),
    KEY organization_units_type_idx (site_key, unit_type, status, sort_order, id),
    CONSTRAINT fk_organization_units_parent
        FOREIGN KEY (parent_id) REFERENCES organization_units(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE organization_site_roots (
    site_key VARCHAR(64) NOT NULL PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    profile VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY organization_site_roots_org_idx (organization_id),
    CONSTRAINT fk_organization_site_roots_org
        FOREIGN KEY (organization_id) REFERENCES organization_units(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE organization_federation_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    local_organization_id BIGINT UNSIGNED NOT NULL,
    relation VARCHAR(16) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    remote_instance_id VARCHAR(36) NOT NULL,
    remote_organization_public_id VARCHAR(36) NOT NULL,
    remote_base_url VARCHAR(500) NOT NULL,
    remote_profile VARCHAR(64) NULL,
    remote_name VARCHAR(255) NULL,
    inbound_scopes_json TEXT NOT NULL,
    outbound_scopes_json TEXT NOT NULL,
    outbound_token_encrypted TEXT NULL,
    sync_cursor TEXT NULL,
    last_seen_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY organization_federation_links_site_instance_unique (site_key, remote_instance_id),
    KEY organization_federation_links_relation_idx (site_key, relation, status, id),
    CONSTRAINT fk_organization_federation_links_local
        FOREIGN KEY (local_organization_id) REFERENCES organization_units(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException(
                "Unsupported migration driver: {$driver}"
            ),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
};
