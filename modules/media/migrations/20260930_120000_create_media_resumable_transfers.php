<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_120000_create_media_resumable_transfers';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE media_resumable_transfers (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL,
    media_public_id VARCHAR(36) NOT NULL,
    provider_id VARCHAR(64) NOT NULL,
    target_key VARCHAR(128) NOT NULL,
    session_encrypted TEXT NOT NULL,
    uploaded_bytes BIGINT NOT NULL DEFAULT 0,
    total_bytes BIGINT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    last_error TEXT NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT media_resumable_transfer_unique
        UNIQUE (
            site_key,
            media_public_id,
            provider_id,
            target_key
        ),
    CONSTRAINT fk_media_resumable_transfer_asset
        FOREIGN KEY (site_key, media_public_id)
        REFERENCES media_assets (site_key, public_id)
        ON DELETE CASCADE
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE media_resumable_transfers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL,
    media_public_id VARCHAR(36) NOT NULL,
    provider_id VARCHAR(64) NOT NULL,
    target_key VARCHAR(128) NOT NULL,
    session_encrypted TEXT NOT NULL,
    uploaded_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_bytes BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    last_error TEXT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY media_resumable_transfer_unique (
        site_key,
        media_public_id,
        provider_id,
        target_key
    ),
    KEY media_resumable_transfer_status_idx (
        status,
        updated_at
    ),
    CONSTRAINT fk_media_resumable_transfer_asset
        FOREIGN KEY (site_key, media_public_id)
        REFERENCES media_assets (site_key, public_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $pdo->exec($sql);

        if ($driver === 'pgsql') {
            $pdo->exec(
                'CREATE INDEX media_resumable_transfer_status_idx
                 ON media_resumable_transfers (
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

        $pdo->exec('DROP TABLE media_resumable_transfers');
    }
};
