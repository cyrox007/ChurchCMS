<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
use PDO;
use RuntimeException;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_100000_create_media_derivatives';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE media_derivatives (
    id BIGSERIAL PRIMARY KEY,
    site_key VARCHAR(64) NOT NULL,
    media_public_id VARCHAR(36) NOT NULL,
    variant VARCHAR(64) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    mime_type VARCHAR(255) NOT NULL,
    bytes BIGINT NOT NULL,
    pixel_width INTEGER NOT NULL,
    pixel_height INTEGER NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT media_derivative_unique
        UNIQUE (site_key, media_public_id, variant),
    CONSTRAINT fk_media_derivative_asset
        FOREIGN KEY (site_key, media_public_id)
        REFERENCES media_assets (site_key, public_id)
        ON DELETE CASCADE
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE media_derivatives (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    site_key VARCHAR(64) NOT NULL,
    media_public_id VARCHAR(36) NOT NULL,
    variant VARCHAR(64) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    mime_type VARCHAR(255) NOT NULL,
    bytes BIGINT UNSIGNED NOT NULL,
    pixel_width INT UNSIGNED NOT NULL,
    pixel_height INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY media_derivative_unique (
        site_key,
        media_public_id,
        variant
    ),
    CONSTRAINT fk_media_derivative_asset
        FOREIGN KEY (site_key, media_public_id)
        REFERENCES media_assets (site_key, public_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $pdo->exec($sql);
    }

    public function down(PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE media_derivatives');
    }
};
