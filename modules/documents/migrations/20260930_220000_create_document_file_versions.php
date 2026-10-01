<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
use PDO;
use RuntimeException;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_220000_create_document_file_versions';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE document_file_versions (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    document_id BIGINT NOT NULL,
    version_number INTEGER NOT NULL,
    media_public_id VARCHAR(36) NOT NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL,
    CONSTRAINT document_file_versions_document_version_unique
        UNIQUE (document_id, version_number),
    CONSTRAINT fk_document_file_versions_document
        FOREIGN KEY (document_id)
        REFERENCES documents(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_document_file_versions_media
        FOREIGN KEY (site_key, media_public_id)
        REFERENCES media_assets (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                <<<'SQL'
CREATE INDEX document_file_versions_document_idx
    ON document_file_versions (
        document_id,
        version_number DESC,
        id DESC
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE document_file_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    document_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    media_public_id VARCHAR(36) NOT NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY document_file_versions_document_version_unique (
        document_id,
        version_number
    ),
    KEY document_file_versions_document_idx (
        document_id,
        version_number,
        id
    ),
    CONSTRAINT fk_document_file_versions_document
        FOREIGN KEY (document_id)
        REFERENCES documents(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_document_file_versions_media
        FOREIGN KEY (site_key, media_public_id)
        REFERENCES media_assets (site_key, public_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер миграции версий файлов документов: {$driver}"
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
                "Неподдерживаемый драйвер миграции версий файлов документов: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE document_file_versions');
    }
};
