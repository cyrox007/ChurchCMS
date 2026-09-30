<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
use PDO;
use RuntimeException;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_090000_create_media_usage_references';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE media_usage_references (
    id BIGSERIAL PRIMARY KEY,
    site_key VARCHAR(64) NOT NULL,
    media_public_id VARCHAR(36) NOT NULL,
    consumer_type VARCHAR(64) NOT NULL,
    consumer_public_id VARCHAR(128) NOT NULL,
    usage_key VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT media_usage_consumer_slot_unique
        UNIQUE (
            site_key,
            consumer_type,
            consumer_public_id,
            usage_key
        ),
    CONSTRAINT fk_media_usage_asset
        FOREIGN KEY (site_key, media_public_id)
        REFERENCES media_assets (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                <<<'SQL'
CREATE INDEX media_usage_asset_idx
    ON media_usage_references (
        site_key,
        media_public_id,
        consumer_type,
        consumer_public_id
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE media_usage_references (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    site_key VARCHAR(64) NOT NULL,
    media_public_id VARCHAR(36) NOT NULL,
    consumer_type VARCHAR(64) NOT NULL,
    consumer_public_id VARCHAR(128) NOT NULL,
    usage_key VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY media_usage_consumer_slot_unique (
        site_key,
        consumer_type,
        consumer_public_id,
        usage_key
    ),
    KEY media_usage_asset_idx (
        site_key,
        media_public_id,
        consumer_type,
        consumer_public_id
    ),
    CONSTRAINT fk_media_usage_asset
        FOREIGN KEY (site_key, media_public_id)
        REFERENCES media_assets (site_key, public_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    public function down(PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE media_usage_references');
    }
};
