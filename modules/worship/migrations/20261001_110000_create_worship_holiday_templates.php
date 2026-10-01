<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
use PDO;
use RuntimeException;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20261001_110000_create_worship_holiday_templates';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE worship_holiday_templates (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    name VARCHAR(255) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
)
SQL,
                <<<'SQL'
CREATE TABLE worship_holiday_template_items (
    id BIGSERIAL PRIMARY KEY,
    template_id BIGINT NOT NULL
        REFERENCES worship_holiday_templates(id)
        ON DELETE CASCADE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    service_type VARCHAR(64) NOT NULL DEFAULT 'service',
    day_offset INTEGER NOT NULL DEFAULT 0,
    local_time VARCHAR(5) NOT NULL,
    duration_minutes INTEGER NULL,
    location_name VARCHAR(255) NULL,
    description_html TEXT NOT NULL DEFAULT ''
)
SQL,
                <<<'SQL'
CREATE TABLE worship_holiday_applications (
    id BIGSERIAL PRIMARY KEY,
    template_id BIGINT NOT NULL
        REFERENCES worship_holiday_templates(id)
        ON DELETE CASCADE,
    template_item_id BIGINT NOT NULL
        REFERENCES worship_holiday_template_items(id)
        ON DELETE CASCADE,
    site_key VARCHAR(64) NOT NULL,
    owner_organization_public_id VARCHAR(36) NOT NULL,
    feast_date DATE NOT NULL,
    worship_public_id VARCHAR(36) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    CONSTRAINT worship_holiday_application_unique
        UNIQUE (
            template_item_id,
            site_key,
            owner_organization_public_id,
            feast_date
        )
)
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE worship_holiday_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    name VARCHAR(255) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE worship_holiday_template_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    service_type VARCHAR(64) NOT NULL DEFAULT 'service',
    day_offset INT NOT NULL DEFAULT 0,
    local_time VARCHAR(5) NOT NULL,
    duration_minutes INT NULL,
    location_name VARCHAR(255) NULL,
    description_html TEXT NOT NULL,
    KEY worship_holiday_template_items_order_idx (
        template_id,
        sort_order,
        id
    ),
    CONSTRAINT worship_holiday_template_items_template_fk
        FOREIGN KEY (template_id)
        REFERENCES worship_holiday_templates(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE worship_holiday_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    template_item_id BIGINT UNSIGNED NOT NULL,
    site_key VARCHAR(64) NOT NULL,
    owner_organization_public_id VARCHAR(36) NOT NULL,
    feast_date DATE NOT NULL,
    worship_public_id VARCHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY worship_holiday_application_unique (
        template_item_id,
        site_key,
        owner_organization_public_id,
        feast_date
    ),
    CONSTRAINT worship_holiday_applications_template_fk
        FOREIGN KEY (template_id)
        REFERENCES worship_holiday_templates(id)
        ON DELETE CASCADE,
    CONSTRAINT worship_holiday_applications_item_fk
        FOREIGN KEY (template_item_id)
        REFERENCES worship_holiday_template_items(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер праздничных шаблонов Worship: {$driver}"
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
                "Неподдерживаемый драйвер праздничных шаблонов Worship: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE worship_holiday_applications');
        $pdo->exec('DROP TABLE worship_holiday_template_items');
        $pdo->exec('DROP TABLE worship_holiday_templates');
    }
};
