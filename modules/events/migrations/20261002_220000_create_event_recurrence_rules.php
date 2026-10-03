<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
use PDO;
use RuntimeException;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20261002_220000_create_event_recurrence_rules';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE event_recurrence_rules (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    title VARCHAR(255) NOT NULL,
    frequency VARCHAR(16) NOT NULL,
    weekday SMALLINT NULL,
    local_time VARCHAR(5) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    duration_minutes INTEGER NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    all_day BOOLEAN NOT NULL DEFAULT FALSE,
    location_name VARCHAR(255) NULL,
    excerpt TEXT NOT NULL DEFAULT '',
    description_html TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT event_recurrence_owner_fk
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
)
SQL,
                <<<'SQL'
CREATE TABLE event_recurrence_occurrences (
    id BIGSERIAL PRIMARY KEY,
    rule_id BIGINT NOT NULL
        REFERENCES event_recurrence_rules(id)
        ON DELETE CASCADE,
    occurrence_date DATE NOT NULL,
    event_public_id VARCHAR(36) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    CONSTRAINT event_recurrence_occurrence_unique
        UNIQUE (rule_id, occurrence_date)
)
SQL,
                'CREATE INDEX event_recurrence_rules_owner_idx ON event_recurrence_rules (site_key, owner_organization_public_id, status, starts_on)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE event_recurrence_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    owner_organization_public_id VARCHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    title VARCHAR(255) NOT NULL,
    frequency VARCHAR(16) NOT NULL,
    weekday TINYINT NULL,
    local_time VARCHAR(5) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    duration_minutes INT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    all_day TINYINT(1) NOT NULL DEFAULT 0,
    location_name VARCHAR(255) NULL,
    excerpt TEXT NOT NULL,
    description_html TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY event_recurrence_rules_owner_idx (
        site_key,
        owner_organization_public_id,
        status,
        starts_on
    ),
    CONSTRAINT event_recurrence_owner_fk
        FOREIGN KEY (site_key, owner_organization_public_id)
        REFERENCES organization_units (site_key, public_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE event_recurrence_occurrences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    rule_id BIGINT UNSIGNED NOT NULL,
    occurrence_date DATE NOT NULL,
    event_public_id VARCHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY event_recurrence_occurrence_unique (
        rule_id,
        occurrence_date
    ),
    CONSTRAINT event_recurrence_occurrence_rule_fk
        FOREIGN KEY (rule_id)
        REFERENCES event_recurrence_rules(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер повторяющихся событий: {$driver}"
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
                "Неподдерживаемый драйвер повторяющихся событий: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE event_recurrence_occurrences');
        $pdo->exec('DROP TABLE event_recurrence_rules');
    }
};
