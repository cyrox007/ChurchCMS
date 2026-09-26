<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260926_130000_create_audit_events';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE audit_events (
    id BIGSERIAL PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    severity VARCHAR(16) NOT NULL,
    actor_user_id BIGINT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
    subject_type VARCHAR(64) NULL,
    subject_id VARCHAR(128) NULL,
    client_ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    created_at TIMESTAMP NOT NULL
);
CREATE INDEX audit_events_created_idx ON audit_events (created_at DESC);
CREATE INDEX audit_events_actor_idx ON audit_events (actor_user_id, created_at DESC);
CREATE INDEX audit_events_subject_idx ON audit_events (subject_type, subject_id, created_at DESC)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    severity VARCHAR(16) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    subject_type VARCHAR(64) NULL,
    subject_id VARCHAR(128) NULL,
    client_ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    metadata_json TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    KEY audit_events_created_idx (created_at),
    KEY audit_events_actor_idx (actor_user_id, created_at),
    KEY audit_events_subject_idx (subject_type, subject_id, created_at),
    CONSTRAINT fk_audit_events_actor FOREIGN KEY (actor_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new RuntimeException("Unsupported migration driver: {$driver}"),
        };

        $pdo->exec($sql);
    }
};
