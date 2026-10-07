<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20261006_090000_create_distribution_webhooks';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        if ($driver === 'pgsql') {
            $pdo->exec(<<<'SQL'
CREATE TABLE distribution_webhook_endpoints (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL,
    name VARCHAR(160) NOT NULL,
    endpoint_url VARCHAR(2048) NOT NULL,
    secret_encrypted TEXT NOT NULL,
    event_types TEXT NOT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
)
SQL);
            $pdo->exec('CREATE INDEX distribution_webhook_endpoints_site_idx ON distribution_webhook_endpoints (site_key, is_active, id)');
            $pdo->exec(<<<'SQL'
CREATE TABLE distribution_webhook_deliveries (
    id BIGSERIAL PRIMARY KEY,
    endpoint_id BIGINT NOT NULL,
    event_id VARCHAR(36) NOT NULL,
    event_type VARCHAR(96) NOT NULL,
    payload_json TEXT NOT NULL,
    status VARCHAR(16) NOT NULL,
    attempt_count INTEGER NOT NULL DEFAULT 0,
    next_attempt_at TIMESTAMP NULL,
    last_attempt_at TIMESTAMP NULL,
    response_status INTEGER NULL,
    last_error_code VARCHAR(64) NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT distribution_webhook_delivery_endpoint_fk
        FOREIGN KEY (endpoint_id) REFERENCES distribution_webhook_endpoints(id) ON DELETE CASCADE,
    CONSTRAINT distribution_webhook_delivery_unique UNIQUE (endpoint_id, event_id)
)
SQL);
            $pdo->exec('CREATE INDEX distribution_webhook_deliveries_due_idx ON distribution_webhook_deliveries (status, next_attempt_at, id)');
        } elseif ($driver === 'mysql') {
            $pdo->exec(<<<'SQL'
CREATE TABLE distribution_webhook_endpoints (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL,
    name VARCHAR(160) NOT NULL,
    endpoint_url VARCHAR(2048) NOT NULL,
    secret_encrypted TEXT NOT NULL,
    event_types TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY distribution_webhook_endpoints_site_idx (site_key, is_active, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
            $pdo->exec(<<<'SQL'
CREATE TABLE distribution_webhook_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    endpoint_id BIGINT UNSIGNED NOT NULL,
    event_id VARCHAR(36) NOT NULL,
    event_type VARCHAR(96) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    status VARCHAR(16) NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NULL,
    last_attempt_at DATETIME NULL,
    response_status INT NULL,
    last_error_code VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY distribution_webhook_delivery_unique (endpoint_id, event_id),
    KEY distribution_webhook_deliveries_due_idx (status, next_attempt_at, id),
    CONSTRAINT distribution_webhook_delivery_endpoint_fk
        FOREIGN KEY (endpoint_id) REFERENCES distribution_webhook_endpoints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        } else {
            throw new \RuntimeException("Неподдерживаемый драйвер миграции: {$driver}");
        }

        $permissions = [
            'distribution_webhooks.read' => 'Просмотр исходящих webhooks',
            'distribution_webhooks.manage' => 'Управление исходящими webhooks',
        ];
        $find = $pdo->prepare(
            'SELECT id FROM permissions WHERE permission_key = :permission_key LIMIT 1'
        );
        $insert = $pdo->prepare(
            'INSERT INTO permissions (permission_key, name) VALUES (:permission_key, :name)'
        );
        foreach ($permissions as $key => $name) {
            $find->execute(['permission_key' => $key]);
            if ($find->fetchColumn() !== false) {
                continue;
            }
            $insert->execute([
                'permission_key' => $key,
                'name' => $name,
            ]);
        }
    }

    public function down(\PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new \RuntimeException("Неподдерживаемый драйвер миграции: {$driver}");
        }

        $pdo->exec('DROP TABLE distribution_webhook_deliveries');
        $pdo->exec('DROP TABLE distribution_webhook_endpoints');
        $pdo->exec(
            "DELETE FROM permissions WHERE permission_key IN ('distribution_webhooks.read','distribution_webhooks.manage')"
        );
    }
};
