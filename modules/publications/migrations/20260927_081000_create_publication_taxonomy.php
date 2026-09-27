<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260927_081000_create_publication_taxonomy';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE publication_categories (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT publication_categories_site_slug_unique UNIQUE (site_key, slug)
)
SQL,
                <<<'SQL'
CREATE TABLE publication_tags (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT publication_tags_site_slug_unique UNIQUE (site_key, slug)
)
SQL,
                <<<'SQL'
CREATE TABLE publication_category_links (
    publication_id BIGINT NOT NULL REFERENCES publications(id) ON DELETE CASCADE,
    category_id BIGINT NOT NULL REFERENCES publication_categories(id) ON DELETE CASCADE,
    PRIMARY KEY (publication_id, category_id)
)
SQL,
                <<<'SQL'
CREATE TABLE publication_tag_links (
    publication_id BIGINT NOT NULL REFERENCES publications(id) ON DELETE CASCADE,
    tag_id BIGINT NOT NULL REFERENCES publication_tags(id) ON DELETE CASCADE,
    PRIMARY KEY (publication_id, tag_id)
)
SQL,
                'CREATE INDEX publication_category_links_category_idx ON publication_category_links (category_id, publication_id)',
                'CREATE INDEX publication_tag_links_tag_idx ON publication_tag_links (tag_id, publication_id)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE publication_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY publication_categories_site_slug_unique (site_key, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE publication_tags (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY publication_tags_site_slug_unique (site_key, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE publication_category_links (
    publication_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (publication_id, category_id),
    KEY publication_category_links_category_idx (category_id, publication_id),
    CONSTRAINT fk_publication_category_links_publication
        FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE,
    CONSTRAINT fk_publication_category_links_category
        FOREIGN KEY (category_id) REFERENCES publication_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE publication_tag_links (
    publication_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (publication_id, tag_id),
    KEY publication_tag_links_tag_idx (tag_id, publication_id),
    CONSTRAINT fk_publication_tag_links_publication
        FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE,
    CONSTRAINT fk_publication_tag_links_tag
        FOREIGN KEY (tag_id) REFERENCES publication_tags(id) ON DELETE CASCADE
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
