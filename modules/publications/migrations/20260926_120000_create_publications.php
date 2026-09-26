<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260926_120000_create_publications';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE publications (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    type VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    title VARCHAR(255) NOT NULL,
    excerpt TEXT NOT NULL DEFAULT '',
    body_html TEXT NOT NULL DEFAULT '',
    author_name VARCHAR(255) NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    syndication_targets TEXT NOT NULL DEFAULT '[]',
    syndication_title VARCHAR(255) NULL,
    syndication_excerpt TEXT NULL,
    CONSTRAINT publications_site_slug_unique UNIQUE (site_key, slug)
);
CREATE INDEX publications_public_list_idx
    ON publications (site_key, status, published_at DESC);
CREATE INDEX publications_updated_idx
    ON publications (site_key, updated_at DESC);
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE publications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    type VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    title VARCHAR(255) NOT NULL,
    excerpt TEXT NOT NULL,
    body_html LONGTEXT NOT NULL,
    author_name VARCHAR(255) NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    syndication_targets TEXT NOT NULL,
    syndication_title VARCHAR(255) NULL,
    syndication_excerpt TEXT NULL,
    UNIQUE KEY publications_site_slug_unique (site_key, slug),
    KEY publications_public_list_idx (site_key, status, published_at),
    KEY publications_updated_idx (site_key, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new RuntimeException("Unsupported migration driver: {$driver}"),
        };

        $pdo->exec($sql);
    }
};
