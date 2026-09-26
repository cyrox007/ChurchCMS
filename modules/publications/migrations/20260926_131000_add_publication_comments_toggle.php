<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;

return new class implements Migration {
    public function id(): string
    {
        return '20260926_131000_add_publication_comments_toggle';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $existsSql = match ($driver) {
            'pgsql' => "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'publications' AND column_name = 'comments_enabled' LIMIT 1",
            'mysql' => "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'publications' AND column_name = 'comments_enabled' LIMIT 1",
            default => throw new \RuntimeException("Unsupported migration driver: {$driver}"),
        };

        if ($pdo->query($existsSql)->fetchColumn() !== false) {
            return;
        }

        $sql = match ($driver) {
            'pgsql' => 'ALTER TABLE publications ADD COLUMN comments_enabled BOOLEAN NOT NULL DEFAULT FALSE',
            'mysql' => 'ALTER TABLE publications ADD COLUMN comments_enabled TINYINT(1) NOT NULL DEFAULT 0',
            default => throw new \RuntimeException("Unsupported migration driver: {$driver}"),
        };

        $pdo->exec($sql);
    }
};
