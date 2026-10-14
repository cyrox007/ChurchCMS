<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20261014_070000_create_vpds_import_mappings';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE vpds_import_mappings (
    id BIGSERIAL PRIMARY KEY,
    organization_id BIGINT NOT NULL,
    source_site VARCHAR(191) NOT NULL,
    entity_type VARCHAR(32) NOT NULL,
    source_id VARCHAR(191) NOT NULL,
    checksum CHAR(64) NOT NULL,
    target_id VARCHAR(191) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT vpds_import_mappings_unique UNIQUE (
        organization_id, source_site, entity_type, source_id
    )
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE vpds_import_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    source_site VARCHAR(191) NOT NULL,
    entity_type VARCHAR(32) NOT NULL,
    source_id VARCHAR(191) NOT NULL,
    checksum CHAR(64) NOT NULL,
    target_id VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY vpds_import_mappings_unique (
        organization_id, source_site, entity_type, source_id
    ),
    KEY vpds_import_mappings_target_idx (organization_id, entity_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $pdo->exec($sql);

        if ($driver === 'pgsql') {
            $pdo->exec(
                'CREATE INDEX vpds_import_mappings_target_idx '
                . 'ON vpds_import_mappings (organization_id, entity_type, target_id)'
            );
        }
    }

    public function down(\PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE vpds_import_mappings');
    }
};
