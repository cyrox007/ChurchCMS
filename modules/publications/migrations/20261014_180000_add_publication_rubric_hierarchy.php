<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20261014_180000_add_publication_rubric_hierarchy';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                'ALTER TABLE publication_categories ADD COLUMN parent_id BIGINT NULL REFERENCES publication_categories(id) ON DELETE SET NULL',
                'ALTER TABLE publication_categories ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0',
                'CREATE INDEX publication_categories_parent_idx ON publication_categories (site_key, parent_id, sort_order, name)',
            ],
            'mysql' => [
                <<<'SQL'
ALTER TABLE publication_categories
    ADD COLUMN parent_id BIGINT UNSIGNED NULL,
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0,
    ADD KEY publication_categories_parent_idx (site_key, parent_id, sort_order, name),
    ADD CONSTRAINT fk_publication_categories_parent
        FOREIGN KEY (parent_id) REFERENCES publication_categories(id) ON DELETE SET NULL
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
