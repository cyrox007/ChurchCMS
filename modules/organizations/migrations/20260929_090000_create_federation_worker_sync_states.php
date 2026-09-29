<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260929_090000_create_federation_worker_sync_states';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE federation_worker_sync_states (
    id BIGSERIAL PRIMARY KEY,
    federation_link_id BIGINT NOT NULL,
    worker_id VARCHAR(64) NOT NULL,
    sync_cursor TEXT NULL,
    last_sync_at TIMESTAMP NULL,
    last_sync_error TEXT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT federation_worker_sync_states_unique
        UNIQUE (federation_link_id, worker_id),
    CONSTRAINT fk_federation_worker_sync_states_link
        FOREIGN KEY (federation_link_id)
        REFERENCES organization_federation_links (id)
        ON DELETE CASCADE
)
SQL,
                <<<'SQL'
CREATE INDEX federation_worker_sync_states_status_idx
    ON federation_worker_sync_states (
        federation_link_id,
        worker_id,
        updated_at
    )
SQL,
                <<<'SQL'
INSERT INTO federation_worker_sync_states (
    federation_link_id,
    worker_id,
    sync_cursor,
    last_sync_at,
    last_sync_error,
    created_at,
    updated_at
)
SELECT
    id,
    'publications',
    sync_cursor,
    last_sync_at,
    last_sync_error,
    updated_at,
    updated_at
FROM organization_federation_links
WHERE sync_cursor IS NOT NULL
   OR last_sync_at IS NOT NULL
   OR last_sync_error IS NOT NULL
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE federation_worker_sync_states (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    federation_link_id BIGINT UNSIGNED NOT NULL,
    worker_id VARCHAR(64) NOT NULL,
    sync_cursor TEXT NULL,
    last_sync_at DATETIME NULL,
    last_sync_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY federation_worker_sync_states_unique (
        federation_link_id,
        worker_id
    ),
    KEY federation_worker_sync_states_status_idx (
        federation_link_id,
        worker_id,
        updated_at
    ),
    CONSTRAINT fk_federation_worker_sync_states_link
        FOREIGN KEY (federation_link_id)
        REFERENCES organization_federation_links (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
INSERT INTO federation_worker_sync_states (
    federation_link_id,
    worker_id,
    sync_cursor,
    last_sync_at,
    last_sync_error,
    created_at,
    updated_at
)
SELECT
    id,
    'publications',
    sync_cursor,
    last_sync_at,
    last_sync_error,
    updated_at,
    updated_at
FROM organization_federation_links
WHERE sync_cursor IS NOT NULL
   OR last_sync_at IS NOT NULL
   OR last_sync_error IS NOT NULL
SQL,
            ],
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
};
