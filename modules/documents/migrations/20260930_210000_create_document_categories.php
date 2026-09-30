<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
use PDO;
use RuntimeException;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_210000_create_document_categories';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE document_categories (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT document_categories_site_slug_unique
        UNIQUE (site_key, slug)
)
SQL,
                <<<'SQL'
CREATE TABLE document_category_links (
    document_id BIGINT NOT NULL
        REFERENCES documents(id)
        ON DELETE CASCADE,
    category_id BIGINT NOT NULL
        REFERENCES document_categories(id)
        ON DELETE CASCADE,
    PRIMARY KEY (document_id, category_id)
)
SQL,
                <<<'SQL'
CREATE INDEX document_category_links_category_idx
    ON document_category_links (
        category_id,
        document_id
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE document_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY document_categories_site_slug_unique (
        site_key,
        slug
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE document_category_links (
    document_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (document_id, category_id),
    KEY document_category_links_category_idx (
        category_id,
        document_id
    ),
    CONSTRAINT fk_document_category_links_document
        FOREIGN KEY (document_id)
        REFERENCES documents(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_document_category_links_category
        FOREIGN KEY (category_id)
        REFERENCES document_categories(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер миграции рубрик документов: {$driver}"
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
                "Неподдерживаемый драйвер миграции рубрик документов: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE document_category_links');
        $pdo->exec('DROP TABLE document_categories');
    }
};
