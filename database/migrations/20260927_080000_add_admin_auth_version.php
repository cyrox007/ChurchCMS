<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260927_080000_add_admin_auth_version';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
ALTER TABLE admin_users
ADD COLUMN auth_version BIGINT NOT NULL DEFAULT 1
SQL,
            'mysql' => <<<'SQL'
ALTER TABLE admin_users
ADD COLUMN auth_version BIGINT UNSIGNED NOT NULL DEFAULT 1
SQL,
            default => throw new RuntimeException(
                "Unsupported migration driver: {$driver}"
            ),
        };

        $pdo->exec($sql);
    }
};
