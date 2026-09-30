<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_130000_create_navigation_menus';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE navigation_menus (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    menu_key VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    enabled SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT navigation_menus_site_key_unique
        UNIQUE (site_key, menu_key)
)
SQL,
                <<<'SQL'
CREATE TABLE navigation_menu_items (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    menu_id BIGINT NOT NULL
        REFERENCES navigation_menus(id)
        ON DELETE CASCADE,
    item_type VARCHAR(20) NOT NULL,
    label VARCHAR(255) NOT NULL,
    page_public_id VARCHAR(36) NULL,
    route_name VARCHAR(100) NULL,
    external_url VARCHAR(1000) NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    enabled SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
)
SQL,
                'CREATE INDEX navigation_menu_items_order_idx ON navigation_menu_items (menu_id, enabled, sort_order, id)',
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE navigation_menus (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    menu_key VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY navigation_menus_site_key_unique (
        site_key,
        menu_key
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE navigation_menu_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    menu_id BIGINT UNSIGNED NOT NULL,
    item_type VARCHAR(20) NOT NULL,
    label VARCHAR(255) NOT NULL,
    page_public_id VARCHAR(36) NULL,
    route_name VARCHAR(100) NULL,
    external_url VARCHAR(1000) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY navigation_menu_items_order_idx (
        menu_id,
        enabled,
        sort_order,
        id
    ),
    CONSTRAINT fk_navigation_menu_items_menu
        FOREIGN KEY (menu_id)
        REFERENCES navigation_menus(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    public function down(\PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE navigation_menu_items');
        $pdo->exec('DROP TABLE navigation_menus');
    }
};
