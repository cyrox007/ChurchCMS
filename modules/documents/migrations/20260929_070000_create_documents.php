<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260929_070000_create_documents';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE documents (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(255) NOT NULL,
    document_type VARCHAR(64) NOT NULL DEFAULT 'document',
    document_number VARCHAR(120) NULL,
    issued_on DATE NULL,
    summary TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT documents_site_public_unique
        UNIQUE (site_key, public_id),
    CONSTRAINT fk_documents_organization_owner
        FOREIGN KEY (
            site_key,
            owner_organization_public_id
        )
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                <<<'SQL'
CREATE INDEX documents_owner_idx
    ON documents (
        site_key,
        owner_organization_public_id,
        status,
        issued_on,
        id
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(255) NOT NULL,
    document_type VARCHAR(64) NOT NULL DEFAULT 'document',
    document_number VARCHAR(120) NULL,
    issued_on DATE NULL,
    summary TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY documents_site_public_unique (
        site_key,
        public_id
    ),
    KEY documents_owner_idx (
        site_key,
        owner_organization_public_id,
        status,
        issued_on,
        id
    ),
    CONSTRAINT fk_documents_organization_owner
        FOREIGN KEY (
            site_key,
            owner_organization_public_id
        )
        REFERENCES organization_units (site_key, public_id)
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
};
