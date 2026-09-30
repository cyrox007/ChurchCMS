<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_130000_create_content_revisions';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE content_revisions (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL,
    entity_type VARCHAR(32) NOT NULL,
    entity_public_id VARCHAR(36) NOT NULL,
    revision_number INTEGER NOT NULL,
    snapshot_json TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL,
    CONSTRAINT content_revision_unique
        UNIQUE (
            site_key,
            entity_type,
            entity_public_id,
            revision_number
        )
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE content_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL,
    entity_type VARCHAR(32) NOT NULL,
    entity_public_id VARCHAR(36) NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY content_revision_unique (
        site_key,
        entity_type,
        entity_public_id,
        revision_number
    ),
    KEY content_revision_lookup_idx (
        site_key,
        entity_type,
        entity_public_id,
        revision_number
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $pdo->exec($sql);

        if ($driver === 'pgsql') {
            $pdo->exec(
                'CREATE INDEX content_revision_lookup_idx
                 ON content_revisions (
                    site_key,
                    entity_type,
                    entity_public_id,
                    revision_number DESC
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

        $pdo->exec('DROP TABLE content_revisions');
    }
};
