<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260928_190000_add_federation_sync_state';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                'ALTER TABLE organization_federation_links '
                . 'ADD COLUMN last_sync_at TIMESTAMP NULL',
                'ALTER TABLE organization_federation_links '
                . 'ADD COLUMN last_sync_error TEXT NULL',
            ],
            'mysql' => [
                'ALTER TABLE organization_federation_links '
                . 'ADD COLUMN last_sync_at DATETIME NULL',
                'ALTER TABLE organization_federation_links '
                . 'ADD COLUMN last_sync_error TEXT NULL',
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
