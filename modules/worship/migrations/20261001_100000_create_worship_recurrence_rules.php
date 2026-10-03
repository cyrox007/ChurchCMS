<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
use PDO;
use RuntimeException;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20261001_100000_create_worship_recurrence_rules';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE worship_recurrence_rules (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    title VARCHAR(255) NOT NULL,
    service_type VARCHAR(64) NOT NULL DEFAULT 'service',
    frequency VARCHAR(16) NOT NULL,
    weekday SMALLINT NULL,
    local_time VARCHAR(5) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    duration_minutes INTEGER NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    location_name VARCHAR(255) NULL,
    description_html TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT worship_recurrence_owner_fk
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                <<<'SQL'
CREATE TABLE worship_recurrence_occurrences (
    id BIGSERIAL PRIMARY KEY,
    rule_id BIGINT NOT NULL
        REFERENCES worship_recurrence_rules(id)
        ON DELETE CASCADE,
    occurrence_date DATE NOT NULL,
    worship_public_id VARCHAR(36) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    CONSTRAINT worship_recurrence_occurrence_unique
        UNIQUE (rule_id, occurrence_date)
)
SQL,
                'CREATE INDEX worship_recurrence_rules_owner_idx ON worship_recurrence_rules (site_key, owner_organization_public_id, status, starts_on)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE worship_recurrence_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    title VARCHAR(255) NOT NULL,
    service_type VARCHAR(64) NOT NULL DEFAULT 'service',
    frequency VARCHAR(16) NOT NULL,
    weekday TINYINT NULL,
    local_time VARCHAR(5) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    duration_minutes INT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    location_name VARCHAR(255) NULL,
    description_html TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY worship_recurrence_rules_owner_idx (
        site_key,
        owner_organization_public_id,
        status,
        starts_on
    ),
    CONSTRAINT worship_recurrence_owner_fk
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE worship_recurrence_occurrences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    rule_id BIGINT UNSIGNED NOT NULL,
    occurrence_date DATE NOT NULL,
    worship_public_id VARCHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY worship_recurrence_occurrence_unique (
        rule_id,
        occurrence_date
    ),
    CONSTRAINT worship_recurrence_occurrence_rule_fk
        FOREIGN KEY (rule_id)
        REFERENCES worship_recurrence_rules(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер повторяющихся богослужений: {$driver}"
            ),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    public function down(PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new RuntimeException(
                "Неподдерживаемый драйвер повторяющихся богослужений: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE worship_recurrence_occurrences');
        $pdo->exec('DROP TABLE worship_recurrence_rules');
    }
};
