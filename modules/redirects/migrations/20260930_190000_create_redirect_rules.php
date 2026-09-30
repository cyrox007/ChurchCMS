<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_190000_create_redirect_rules';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE redirect_rules (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    source_path VARCHAR(1000) NOT NULL,
    target_path VARCHAR(2000) NOT NULL,
    status_code INTEGER NOT NULL DEFAULT 301,
    enabled SMALLINT NOT NULL DEFAULT 1,
    hit_count BIGINT NOT NULL DEFAULT 0,
    last_hit_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT redirect_rules_site_source_unique
        UNIQUE (site_key, source_path)
)
SQL,
                'CREATE INDEX redirect_rules_enabled_source_idx ON redirect_rules (site_key, enabled, source_path)',
                <<<'SQL'
INSERT INTO permissions (permission_key, name)
VALUES ('redirects.manage', 'Управление редиректами')
ON CONFLICT (permission_key) DO NOTHING
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE redirect_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    source_path VARCHAR(1000) NOT NULL,
    target_path VARCHAR(2000) NOT NULL,
    status_code INT NOT NULL DEFAULT 301,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_hit_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY redirect_rules_site_source_unique (
        site_key,
        source_path
    ),
    KEY redirect_rules_enabled_source_idx (
        site_key,
        enabled,
        source_path
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
INSERT IGNORE INTO permissions (permission_key, name)
VALUES ('redirects.manage', 'Управление редиректами')
SQL,
            ],
            default => throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    public function down(\PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE redirect_rules');

        $statement = $pdo->prepare(
            'DELETE FROM permissions
             WHERE permission_key = :permission_key'
        );
        $statement->execute([
            'permission_key' => 'redirects.manage',
        ]);
    }
};
