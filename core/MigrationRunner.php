<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly string $root,
    ) {
    }

    /**
     * @return list<string> applied migration ids
     */
    public function migrate(): array
    {
        $pdo = $this->database->connection();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $this->ensureTable($pdo, $driver);
        $applied = $this->applied($pdo);
        $migrations = $this->discover();
        $executed = [];

        foreach ($migrations as $migration) {
            $id = $migration->id();
            if (isset($applied[$id])) {
                continue;
            }

            $transactional = $driver !== 'mysql';

            try {
                if ($transactional) {
                    $pdo->beginTransaction();
                }

                $migration->up($pdo, $driver);

                $statement = $pdo->prepare(
                    'INSERT INTO churchcms_migrations (migration_id, applied_at) VALUES (:id, :applied_at)'
                );
                $statement->execute([
                    'id' => $id,
                    'applied_at' => gmdate('Y-m-d H:i:s'),
                ]);

                if ($transactional) {
                    $pdo->commit();
                }

                $executed[] = $id;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw new RuntimeException("Migration failed: {$id}", 0, $e);
            }
        }

        return $executed;
    }

    /**
     * Validate all discoverable migration files without connecting to the database.
     *
     * @return list<string> migration ids in execution order
     */
    public function validate(): array
    {
        return array_map(
            static fn(Migration $migration): string => $migration->id(),
            $this->discover(),
        );
    }

    /**
     * @return list<Migration>
     */
    private function discover(): array
    {
        $paths = [];

        $global = $this->root . '/database/migrations';
        if (is_dir($global)) {
            foreach (glob($global . '/*.php') ?: [] as $file) {
                $paths[] = $file;
            }
        }

        foreach (glob($this->root . '/modules/*/migrations') ?: [] as $dir) {
            if (!is_dir($dir) || is_link($dir)) {
                continue;
            }
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $paths[] = $file;
            }
        }

        sort($paths, SORT_STRING);

        $migrations = [];
        $ids = [];
        foreach ($paths as $file) {
            if (!is_file($file) || is_link($file)) {
                continue;
            }

            $migration = require $file;
            if (!$migration instanceof Migration) {
                throw new RuntimeException("Migration file must return Migration: {$file}");
            }

            $id = $migration->id();
            if (preg_match('/^[0-9]{8}_[0-9]{6}_[a-z0-9_]{1,80}$/D', $id) !== 1) {
                throw new RuntimeException("Invalid migration id: {$id}");
            }

            if (isset($ids[$id])) {
                throw new RuntimeException("Duplicate migration id: {$id}");
            }

            $ids[$id] = true;
            $migrations[] = $migration;
        }

        usort(
            $migrations,
            static fn(Migration $a, Migration $b): int => strcmp($a->id(), $b->id()),
        );

        return $migrations;
    }

    private function ensureTable(PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS churchcms_migrations (
    migration_id VARCHAR(128) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS churchcms_migrations (
    migration_id VARCHAR(128) PRIMARY KEY,
    applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new RuntimeException("Unsupported migration driver: {$driver}"),
        };

        $pdo->exec($sql);
    }

    /**
     * @return array<string,true>
     */
    private function applied(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT migration_id FROM churchcms_migrations ORDER BY migration_id')->fetchAll();
        $result = [];

        foreach ($rows as $row) {
            $id = (string) ($row['migration_id'] ?? '');
            if ($id !== '') {
                $result[$id] = true;
            }
        }

        return $result;
    }
}
