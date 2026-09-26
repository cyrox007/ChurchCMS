<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260926_150000_create_publication_seo';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE publication_seo (
    publication_id BIGINT PRIMARY KEY REFERENCES publications(id) ON DELETE CASCADE,
    seo_title VARCHAR(255) NULL,
    seo_description TEXT NULL,
    seo_keywords TEXT NULL,
    canonical_url TEXT NULL,
    social_title VARCHAR(255) NULL,
    social_description TEXT NULL,
    social_image_url TEXT NULL,
    robots_index BOOLEAN NOT NULL DEFAULT TRUE,
    robots_follow BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMP NOT NULL
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE publication_seo (
    publication_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    seo_title VARCHAR(255) NULL,
    seo_description TEXT NULL,
    seo_keywords TEXT NULL,
    canonical_url TEXT NULL,
    social_title VARCHAR(255) NULL,
    social_description TEXT NULL,
    social_image_url TEXT NULL,
    robots_index TINYINT(1) NOT NULL DEFAULT 1,
    robots_follow TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_publication_seo_publication FOREIGN KEY (publication_id)
        REFERENCES publications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new RuntimeException("Unsupported migration driver: {$driver}"),
        };

        $pdo->exec($sql);
    }
};
