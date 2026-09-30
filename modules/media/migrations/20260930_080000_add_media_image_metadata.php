<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
use PDO;
use RuntimeException;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_080000_add_media_image_metadata';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statement = match ($driver) {
            'pgsql' => <<<'SQL'
ALTER TABLE media_assets
    ADD COLUMN pixel_width INTEGER NULL,
    ADD COLUMN pixel_height INTEGER NULL
SQL,
            'mysql' => <<<'SQL'
ALTER TABLE media_assets
    ADD COLUMN pixel_width INT NULL,
    ADD COLUMN pixel_height INT NULL
SQL,
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $pdo->exec($statement);
    }

    public function down(PDO $pdo, string $driver): void
    {
        $statement = match ($driver) {
            'pgsql' => <<<'SQL'
ALTER TABLE media_assets
    DROP COLUMN pixel_height,
    DROP COLUMN pixel_width
SQL,
            'mysql' => <<<'SQL'
ALTER TABLE media_assets
    DROP COLUMN pixel_height,
    DROP COLUMN pixel_width
SQL,
            default => throw new RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $pdo->exec($statement);
    }
};
