<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20261005_190000_create_education_admissions';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE education_admissions (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    program_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(500) NOT NULL,
    academic_year VARCHAR(32) NOT NULL,
    starts_on DATE NULL,
    ends_on DATE NULL,
    budget_seats INTEGER NOT NULL DEFAULT 0,
    paid_seats INTEGER NOT NULL DEFAULT 0,
    tuition_note TEXT NOT NULL DEFAULT '',
    requirements TEXT NOT NULL DEFAULT '',
    entrance_tests TEXT NOT NULL DEFAULT '',
    contact_note TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT education_admissions_site_public_unique UNIQUE (site_key, public_id),
    CONSTRAINT fk_education_admissions_program
        FOREIGN KEY (site_key, program_public_id)
        REFERENCES education_programs (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                'CREATE INDEX education_admissions_public_idx ON education_admissions (site_key, status, academic_year, sort_order, id)',
                'CREATE INDEX education_admissions_program_idx ON education_admissions (site_key, program_public_id, status, sort_order, id)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE education_admissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    program_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(500) NOT NULL,
    academic_year VARCHAR(32) NOT NULL,
    starts_on DATE NULL,
    ends_on DATE NULL,
    budget_seats INT NOT NULL DEFAULT 0,
    paid_seats INT NOT NULL DEFAULT 0,
    tuition_note TEXT NOT NULL,
    requirements TEXT NOT NULL,
    entrance_tests TEXT NOT NULL,
    contact_note TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY education_admissions_site_public_unique (site_key, public_id),
    KEY education_admissions_public_idx (site_key, status, academic_year, sort_order, id),
    KEY education_admissions_program_idx (site_key, program_public_id, status, sort_order, id),
    CONSTRAINT fk_education_admissions_program
        FOREIGN KEY (site_key, program_public_id)
        REFERENCES education_programs (site_key, public_id)
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
            'education_admissions.read' => 'Просмотр приёмной кампании',
            'education_admissions.manage' => 'Управление приёмной кампанией',
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
