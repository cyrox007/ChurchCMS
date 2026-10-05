<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20261005_220000_create_education_schedules';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE education_schedules (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    program_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(500) NOT NULL,
    starts_at_utc TIMESTAMP NOT NULL,
    ends_at_utc TIMESTAMP NOT NULL,
    timezone VARCHAR(100) NOT NULL,
    location VARCHAR(500) NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT education_schedules_site_public_unique UNIQUE (site_key, public_id),
    CONSTRAINT fk_education_schedules_program
        FOREIGN KEY (site_key, program_public_id)
        REFERENCES education_programs (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                'CREATE INDEX education_schedules_public_idx ON education_schedules (site_key, status, starts_at_utc, id)',
                'CREATE INDEX education_schedules_program_idx ON education_schedules (site_key, program_public_id, status, starts_at_utc, id)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE education_schedules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    program_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    title VARCHAR(500) NOT NULL,
    starts_at_utc DATETIME NOT NULL,
    ends_at_utc DATETIME NOT NULL,
    timezone VARCHAR(100) NOT NULL,
    location VARCHAR(500) NOT NULL DEFAULT '',
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY education_schedules_site_public_unique (site_key, public_id),
    KEY education_schedules_public_idx (site_key, status, starts_at_utc, id),
    KEY education_schedules_program_idx (site_key, program_public_id, status, starts_at_utc, id),
    CONSTRAINT fk_education_schedules_program
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
            'education_schedules.read' => 'Просмотр образовательного расписания',
            'education_schedules.manage' => 'Управление образовательным расписанием',
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
