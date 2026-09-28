<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260928_130000_create_people_and_appointments';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE people (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    display_name VARCHAR(255) NOT NULL,
    first_name VARCHAR(120) NULL,
    middle_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    biography_html TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT people_site_public_unique
        UNIQUE (site_key, public_id),
    CONSTRAINT fk_people_organization_owner
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                <<<'SQL'
CREATE INDEX people_organization_owner_idx
    ON people (
        site_key,
        owner_organization_public_id,
        status,
        display_name,
        id
    )
SQL,
                <<<'SQL'
CREATE TABLE person_appointments (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    person_public_id VARCHAR(36) NOT NULL,
    organization_public_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    appointment_type VARCHAR(64) NOT NULL DEFAULT 'position',
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    started_on DATE NULL,
    ended_on DATE NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT fk_person_appointments_person
        FOREIGN KEY (site_key, person_public_id)
        REFERENCES people (site_key, public_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_person_appointments_organization
        FOREIGN KEY (site_key, organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                <<<'SQL'
CREATE INDEX person_appointments_organization_idx
    ON person_appointments (
        site_key,
        organization_public_id,
        status,
        sort_order,
        id
    )
SQL,
                <<<'SQL'
CREATE INDEX person_appointments_person_idx
    ON person_appointments (
        site_key,
        person_public_id,
        status,
        sort_order,
        id
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE people (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    display_name VARCHAR(255) NOT NULL,
    first_name VARCHAR(120) NULL,
    middle_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    biography_html TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY people_site_public_unique (site_key, public_id),
    KEY people_organization_owner_idx (
        site_key,
        owner_organization_public_id,
        status,
        display_name,
        id
    ),
    CONSTRAINT fk_people_organization_owner
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE person_appointments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    person_public_id VARCHAR(36) NOT NULL,
    organization_public_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    appointment_type VARCHAR(64) NOT NULL DEFAULT 'position',
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    started_on DATE NULL,
    ended_on DATE NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY person_appointments_organization_idx (
        site_key,
        organization_public_id,
        status,
        sort_order,
        id
    ),
    KEY person_appointments_person_idx (
        site_key,
        person_public_id,
        status,
        sort_order,
        id
    ),
    CONSTRAINT fk_person_appointments_person
        FOREIGN KEY (site_key, person_public_id)
        REFERENCES people (site_key, public_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_person_appointments_organization
        FOREIGN KEY (site_key, organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
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
