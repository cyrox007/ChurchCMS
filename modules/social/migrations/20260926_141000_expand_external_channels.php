<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260926_141000_expand_external_channels';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                "ALTER TABLE social_connections ADD COLUMN connection_kind VARCHAR(32) NOT NULL DEFAULT 'social'",
                "ALTER TABLE social_connections ADD COLUMN outbound_enabled BOOLEAN NOT NULL DEFAULT TRUE",
                "ALTER TABLE social_connections ADD COLUMN inbound_enabled BOOLEAN NOT NULL DEFAULT FALSE",
                "ALTER TABLE social_connections ADD COLUMN inbound_policy VARCHAR(20) NOT NULL DEFAULT 'review'",
                <<<'SQL'
CREATE TABLE external_channel_items (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    connection_id BIGINT NOT NULL REFERENCES social_connections(id) ON DELETE CASCADE,
    remote_id VARCHAR(255) NOT NULL,
    kind VARCHAR(32) NOT NULL,
    title TEXT NULL,
    body_text TEXT NOT NULL DEFAULT '',
    canonical_url TEXT NULL,
    media_json TEXT NOT NULL DEFAULT '[]',
    payload_json TEXT NOT NULL DEFAULT '{}',
    remote_published_at TIMESTAMP NULL,
    remote_updated_at TIMESTAMP NULL,
    fingerprint VARCHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    linked_publication_id BIGINT NULL REFERENCES publications(id) ON DELETE SET NULL,
    discovered_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT external_channel_items_remote_unique UNIQUE (connection_id, remote_id)
)
SQL,
                <<<'SQL'
CREATE TABLE external_channel_sync_state (
    connection_id BIGINT PRIMARY KEY REFERENCES social_connections(id) ON DELETE CASCADE,
    cursor_value TEXT NULL,
    last_sync_at TIMESTAMP NULL,
    last_error VARCHAR(500) NULL,
    updated_at TIMESTAMP NOT NULL
)
SQL,
                'CREATE INDEX external_channel_items_inbox_idx ON external_channel_items (status, discovered_at DESC)',
            ],
            'mysql' => [
                "ALTER TABLE social_connections ADD COLUMN connection_kind VARCHAR(32) NOT NULL DEFAULT 'social'",
                "ALTER TABLE social_connections ADD COLUMN outbound_enabled TINYINT(1) NOT NULL DEFAULT 1",
                "ALTER TABLE social_connections ADD COLUMN inbound_enabled TINYINT(1) NOT NULL DEFAULT 0",
                "ALTER TABLE social_connections ADD COLUMN inbound_policy VARCHAR(20) NOT NULL DEFAULT 'review'",
                <<<'SQL'
CREATE TABLE external_channel_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    connection_id BIGINT UNSIGNED NOT NULL,
    remote_id VARCHAR(255) NOT NULL,
    kind VARCHAR(32) NOT NULL,
    title TEXT NULL,
    body_text TEXT NOT NULL,
    canonical_url TEXT NULL,
    media_json TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    remote_published_at DATETIME NULL,
    remote_updated_at DATETIME NULL,
    fingerprint VARCHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    linked_publication_id BIGINT UNSIGNED NULL,
    discovered_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY external_channel_items_remote_unique (connection_id, remote_id),
    KEY external_channel_items_inbox_idx (status, discovered_at),
    CONSTRAINT fk_external_item_connection FOREIGN KEY (connection_id) REFERENCES social_connections(id) ON DELETE CASCADE,
    CONSTRAINT fk_external_item_publication FOREIGN KEY (linked_publication_id) REFERENCES publications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE external_channel_sync_state (
    connection_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    cursor_value TEXT NULL,
    last_sync_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_external_sync_connection FOREIGN KEY (connection_id) REFERENCES social_connections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException("Unsupported migration driver: {$driver}"),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
};
