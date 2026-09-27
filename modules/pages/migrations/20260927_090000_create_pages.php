<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260927_090000_create_pages';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE pages (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    parent_id BIGINT NULL REFERENCES pages(id) ON DELETE RESTRICT,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    slug VARCHAR(180) NOT NULL,
    path VARCHAR(700) NOT NULL,
    title VARCHAR(255) NOT NULL,
    navigation_title VARCHAR(255) NULL,
    body_html TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT pages_site_path_unique UNIQUE (site_key, path)
)
SQL,
                'CREATE INDEX pages_tree_order_idx ON pages (site_key, parent_id, sort_order, id)',
                'CREATE INDEX pages_status_idx ON pages (site_key, status, parent_id, sort_order, id)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE pages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    parent_id BIGINT UNSIGNED NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    slug VARCHAR(180) NOT NULL,
    path VARCHAR(700) NOT NULL,
    title VARCHAR(255) NOT NULL,
    navigation_title VARCHAR(255) NULL,
    body_html TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY pages_site_path_unique (site_key, path),
    KEY pages_tree_order_idx (site_key, parent_id, sort_order, id),
    KEY pages_status_idx (site_key, status, parent_id, sort_order, id),
    CONSTRAINT fk_pages_parent
        FOREIGN KEY (parent_id) REFERENCES pages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException(
                "Unsupported migration driver: {$driver}"
            ),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
};
