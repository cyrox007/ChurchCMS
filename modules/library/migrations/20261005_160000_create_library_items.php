<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20261005_160000_create_library_items';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE library_items (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(500) NOT NULL,
    author_name VARCHAR(500) NULL,
    publisher_name VARCHAR(255) NULL,
    publication_year INTEGER NULL,
    isbn VARCHAR(32) NULL,
    shelf_code VARCHAR(100) NULL,
    availability_note VARCHAR(500) NULL,
    summary TEXT NOT NULL DEFAULT '',
    description_html TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT library_items_site_public_unique UNIQUE (site_key, public_id),
    CONSTRAINT fk_library_items_organization_owner
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                'CREATE INDEX library_items_public_idx ON library_items (site_key, status, sort_order, title, id)',
                'CREATE INDEX library_items_owner_idx ON library_items (site_key, owner_organization_public_id, status, sort_order, id)',
                'CREATE INDEX library_items_isbn_idx ON library_items (site_key, isbn)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE library_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(500) NOT NULL,
    author_name VARCHAR(500) NULL,
    publisher_name VARCHAR(255) NULL,
    publication_year INT NULL,
    isbn VARCHAR(32) NULL,
    shelf_code VARCHAR(100) NULL,
    availability_note VARCHAR(500) NULL,
    summary TEXT NOT NULL,
    description_html TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY library_items_site_public_unique (site_key, public_id),
    KEY library_items_public_idx (site_key, status, sort_order, title(191), id),
    KEY library_items_owner_idx (site_key, owner_organization_public_id, status, sort_order, id),
    KEY library_items_isbn_idx (site_key, isbn),
    CONSTRAINT fk_library_items_organization_owner
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException("Неподдерживаемый драйвер миграции: {$driver}"),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $permissions = [
            'library.read' => 'Просмотр библиотеки',
            'library.manage' => 'Управление библиотекой',
        ];
        $find = $pdo->prepare('SELECT id FROM permissions WHERE permission_key = :permission_key LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO permissions (permission_key, name) VALUES (:permission_key, :name)');
        foreach ($permissions as $key => $name) {
            $find->execute(['permission_key' => $key]);
            if ($find->fetchColumn() !== false) {
                continue;
            }
            $insert->execute(['permission_key' => $key, 'name' => $name]);
        }
    }
};
