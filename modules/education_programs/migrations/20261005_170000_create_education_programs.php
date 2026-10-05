<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20261005_170000_create_education_programs';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE education_programs (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(500) NOT NULL,
    education_level VARCHAR(100) NOT NULL,
    study_form VARCHAR(100) NOT NULL,
    duration_months INTEGER NULL,
    qualification VARCHAR(255) NULL,
    admission_note TEXT NOT NULL DEFAULT '',
    summary TEXT NOT NULL DEFAULT '',
    description_html TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT education_programs_site_public_unique UNIQUE (site_key, public_id),
    CONSTRAINT fk_education_programs_organization_owner
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                'CREATE INDEX education_programs_public_idx ON education_programs (site_key, status, sort_order, title, id)',
                'CREATE INDEX education_programs_owner_idx ON education_programs (site_key, owner_organization_public_id, status, sort_order, id)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE education_programs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(500) NOT NULL,
    education_level VARCHAR(100) NOT NULL,
    study_form VARCHAR(100) NOT NULL,
    duration_months INT NULL,
    qualification VARCHAR(255) NULL,
    admission_note TEXT NOT NULL,
    summary TEXT NOT NULL,
    description_html TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY education_programs_site_public_unique (site_key, public_id),
    KEY education_programs_public_idx (site_key, status, sort_order, title(191), id),
    KEY education_programs_owner_idx (site_key, owner_organization_public_id, status, sort_order, id),
    CONSTRAINT fk_education_programs_organization_owner
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
            'education_programs.read' => 'Просмотр образовательных программ',
            'education_programs.manage' => 'Управление образовательными программами',
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
