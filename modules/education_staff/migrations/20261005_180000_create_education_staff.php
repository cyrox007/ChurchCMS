<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20261005_180000_create_education_staff';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE education_chairs (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    name VARCHAR(500) NOT NULL,
    short_name VARCHAR(120) NULL,
    description_html TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT education_chairs_site_public_unique UNIQUE (site_key, public_id),
    CONSTRAINT fk_education_chairs_organization_owner
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                'CREATE INDEX education_chairs_public_idx ON education_chairs (site_key, status, sort_order, name, id)',
                'CREATE INDEX education_chairs_owner_idx ON education_chairs (site_key, owner_organization_public_id, status, sort_order, id)',
                <<<'SQL'
CREATE TABLE education_teacher_assignments (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    chair_public_id VARCHAR(36) NOT NULL,
    person_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    position_title VARCHAR(255) NOT NULL,
    academic_degree VARCHAR(255) NULL,
    academic_title VARCHAR(255) NULL,
    disciplines TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT education_teacher_assignments_site_public_unique UNIQUE (site_key, public_id),
    CONSTRAINT education_teacher_assignments_person_unique UNIQUE (site_key, chair_public_id, person_public_id),
    CONSTRAINT fk_education_teacher_assignments_chair
        FOREIGN KEY (site_key, chair_public_id)
        REFERENCES education_chairs (site_key, public_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_education_teacher_assignments_person
        FOREIGN KEY (site_key, person_public_id)
        REFERENCES people (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                'CREATE INDEX education_teacher_assignments_chair_idx ON education_teacher_assignments (site_key, chair_public_id, status, sort_order, id)',
                'CREATE INDEX education_teacher_assignments_person_idx ON education_teacher_assignments (site_key, person_public_id, status, id)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE education_chairs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    name VARCHAR(500) NOT NULL,
    short_name VARCHAR(120) NULL,
    description_html TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY education_chairs_site_public_unique (site_key, public_id),
    KEY education_chairs_public_idx (site_key, status, sort_order, name(191), id),
    KEY education_chairs_owner_idx (site_key, owner_organization_public_id, status, sort_order, id),
    CONSTRAINT fk_education_chairs_organization_owner
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE education_teacher_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    chair_public_id VARCHAR(36) NOT NULL,
    person_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    position_title VARCHAR(255) NOT NULL,
    academic_degree VARCHAR(255) NULL,
    academic_title VARCHAR(255) NULL,
    disciplines TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY education_teacher_assignments_site_public_unique (site_key, public_id),
    UNIQUE KEY education_teacher_assignments_person_unique (site_key, chair_public_id, person_public_id),
    KEY education_teacher_assignments_chair_idx (site_key, chair_public_id, status, sort_order, id),
    KEY education_teacher_assignments_person_idx (site_key, person_public_id, status, id),
    CONSTRAINT fk_education_teacher_assignments_chair
        FOREIGN KEY (site_key, chair_public_id)
        REFERENCES education_chairs (site_key, public_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_education_teacher_assignments_person
        FOREIGN KEY (site_key, person_public_id)
        REFERENCES people (site_key, public_id)
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
            'education_staff.read' => 'Просмотр кафедр и преподавателей',
            'education_staff.manage' => 'Управление кафедрами и преподавателями',
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
