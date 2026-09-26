<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260926_140000_create_social_publishing';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE social_connections (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    provider VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    target_ref VARCHAR(255) NOT NULL,
    token_encrypted TEXT NOT NULL,
    settings_json TEXT NOT NULL DEFAULT '{}',
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT social_connections_provider_target_unique UNIQUE (provider, target_ref)
)
SQL,
                <<<'SQL'
CREATE TABLE publication_social_posts (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    publication_id BIGINT NOT NULL REFERENCES publications(id) ON DELETE CASCADE,
    connection_id BIGINT NOT NULL REFERENCES social_connections(id) ON DELETE CASCADE,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    custom_text TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'idle',
    remote_post_id VARCHAR(255) NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    queued_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT publication_social_posts_unique UNIQUE (publication_id, connection_id)
)
SQL,
                'CREATE INDEX publication_social_posts_queue_idx ON publication_social_posts (status, queued_at, id)',
                <<<'SQL'
INSERT INTO permissions (permission_key, name)
VALUES
    ('social.manage', 'Управление подключениями соцсетей'),
    ('social.publish', 'Публикация материалов в соцсети')
ON CONFLICT (permission_key) DO NOTHING
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE social_connections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    provider VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    target_ref VARCHAR(255) NOT NULL,
    token_encrypted TEXT NOT NULL,
    settings_json TEXT NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY social_connections_provider_target_unique (provider, target_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE publication_social_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    publication_id BIGINT UNSIGNED NOT NULL,
    connection_id BIGINT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    custom_text TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'idle',
    remote_post_id VARCHAR(255) NULL,
    attempts INT NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    queued_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY publication_social_posts_unique (publication_id, connection_id),
    KEY publication_social_posts_queue_idx (status, queued_at, id),
    CONSTRAINT fk_social_posts_publication FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE,
    CONSTRAINT fk_social_posts_connection FOREIGN KEY (connection_id) REFERENCES social_connections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
INSERT IGNORE INTO permissions (permission_key, name)
VALUES
    ('social.manage', 'Управление подключениями соцсетей'),
    ('social.publish', 'Публикация материалов в соцсети')
SQL,
            ],
            default => throw new RuntimeException("Unsupported migration driver: {$driver}"),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
};
