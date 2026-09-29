<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260929_081000_add_document_visibility';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
ALTER TABLE documents
    ADD COLUMN visibility VARCHAR(16) NOT NULL DEFAULT 'private'
SQL,
                <<<'SQL'
CREATE INDEX documents_visibility_idx
    ON documents (
        site_key,
        visibility,
        status,
        updated_at,
        id
    )
SQL,
            ],
            'mysql' => [
                <<<'SQL'
ALTER TABLE documents
    ADD COLUMN visibility VARCHAR(16) NOT NULL DEFAULT 'private'
SQL,
                <<<'SQL'
CREATE INDEX documents_visibility_idx
    ON documents (
        site_key,
        visibility,
        status,
        updated_at,
        id
    )
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
