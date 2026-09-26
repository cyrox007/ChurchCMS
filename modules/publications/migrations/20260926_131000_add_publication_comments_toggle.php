<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260926_131000_add_publication_comments_toggle';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => 'ALTER TABLE publications ADD COLUMN comments_enabled BOOLEAN NOT NULL DEFAULT FALSE',
            'mysql' => 'ALTER TABLE publications ADD COLUMN comments_enabled TINYINT(1) NOT NULL DEFAULT 0',
            default => throw new RuntimeException("Unsupported migration driver: {$driver}"),
        };

        $pdo->exec($sql);
    }
};
