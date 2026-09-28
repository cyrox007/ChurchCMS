<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260928_180000_create_federation_remote_projections';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE federation_remote_projections (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    federation_link_id BIGINT NOT NULL
        REFERENCES organization_federation_links(id) ON DELETE CASCADE,
    object_type VARCHAR(64) NOT NULL,
    remote_public_id VARCHAR(255) NOT NULL,
    remote_owner_organization_public_id VARCHAR(36) NULL,
    canonical_url VARCHAR(1000) NULL,
    state VARCHAR(16) NOT NULL,
    payload_json TEXT NULL,
    remote_updated_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT federation_remote_projections_remote_unique
        UNIQUE (federation_link_id, object_type, remote_public_id)
)
SQL,
                <<<'SQL'
CREATE INDEX federation_remote_projections_list_idx
    ON federation_remote_projections (
        federation_link_id,
        object_type,
        state,
        remote_updated_at DESC,
        id DESC
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE federation_remote_projections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    federation_link_id BIGINT UNSIGNED NOT NULL,
    object_type VARCHAR(64) NOT NULL,
    remote_public_id VARCHAR(255) NOT NULL,
    remote_owner_organization_public_id VARCHAR(36) NULL,
    canonical_url VARCHAR(1000) NULL,
    state VARCHAR(16) NOT NULL,
    payload_json LONGTEXT NULL,
    remote_updated_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY federation_remote_projections_remote_unique (
        federation_link_id,
        object_type,
        remote_public_id
    ),
    KEY federation_remote_projections_list_idx (
        federation_link_id,
        object_type,
        state,
        remote_updated_at,
        id
    ),
    CONSTRAINT fk_federation_remote_projection_link
        FOREIGN KEY (federation_link_id)
        REFERENCES organization_federation_links(id)
        ON DELETE CASCADE
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
