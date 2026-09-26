<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use PDO;
use RuntimeException;
use Throwable;

final class DatabaseRestoreManager
{
    private const BACKUP_ID_PATTERN = '/^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$/D';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly string $root,
        private readonly string $backupRoot,
    ) {
    }

    /**
     * Восстанавливает только данные БД из уже проверенной резервной копии.
     *
     * @return array{tables:int, rows:int}
     */
    public function restore(string $backupId): array
    {
        $path = $this->verifiedBackupPath($backupId);
        $database = $this->databaseManifest($path);

        $pdo = $this->database->connection();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (($database['driver'] ?? null) !== $driver) {
            throw new RuntimeException('Драйвер базы данных резервной копии не совпадает с текущей установкой.');
        }

        if (($database['encoding'] ?? null) !== 'base64-or-null') {
            throw new RuntimeException('Кодировка дампа базы данных не поддерживается.');
        }

        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new RuntimeException('Восстановление этой базы данных не поддерживается.');
        }

        $tables = $this->normalizeTables($database['tables'] ?? null);
        $this->assertSchemaMatches($pdo, $driver, $tables);

        $rows = $driver === 'pgsql'
            ? $this->restorePostgres($pdo, $path, $tables)
            : $this->restoreMysql($pdo, $path, $tables);

        $this->verifyRestoredRows($pdo, $driver, $tables);

        return [
            'tables' => count($tables),
            'rows' => $rows,
        ];
    }

    private function verifiedBackupPath(string $backupId): string
    {
        if (preg_match(self::BACKUP_ID_PATTERN, $backupId) !== 1) {
            throw new RuntimeException('Некорректный идентификатор резервной копии.');
        }

        (new BackupManager(
            $this->database,
            $this->root,
            $this->backupRoot,
        ))->verify($backupId);

        $root = realpath($this->backupRoot);
        $path = $root !== false
            ? realpath($root . DIRECTORY_SEPARATOR . $backupId)
            : false;

        if ($root === false || $path === false || !is_dir($path) || is_link($path)) {
            throw new RuntimeException('Не удалось открыть проверенную резервную копию.');
        }

        if (dirname($path) !== $root) {
            throw new RuntimeException('Путь резервной копии вышел за разрешённый каталог.');
        }

        return $path;
    }

    /**
     * @return array<string,mixed>
     */
    private function databaseManifest(string $backupPath): array
    {
        $manifestPath = $backupPath . DIRECTORY_SEPARATOR . 'manifest.json';
        $raw = file_get_contents($manifestPath);

        if (!is_string($raw)) {
            throw new RuntimeException('Не удалось прочитать манифест резервной копии.');
        }

        $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        $database = is_array($manifest) ? ($manifest['database'] ?? null) : null;

        if (!is_array($database)) {
            throw new RuntimeException('В манифесте отсутствует описание базы данных.');
        }

        return $database;
    }

    /**
     * @return array<string,array{name:string,path:string,columns:list<string>,rows:int}>
     */
    private function normalizeTables(mixed $rawTables): array
    {
        if (!is_array($rawTables)) {
            throw new RuntimeException('В манифесте отсутствует список таблиц.');
        }

        $tables = [];

        foreach ($rawTables as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Описание таблицы в манифесте повреждено.');
            }

            $name = (string) ($entry['name'] ?? '');
            $path = (string) ($entry['path'] ?? '');
            $columns = $entry['columns'] ?? null;
            $rows = $entry['rows'] ?? null;

            $this->assertIdentifier($name);

            if (
                !is_array($columns)
                || !is_int($rows)
                || $rows < 0
                || !$this->safeDatabasePath($path, $name)
            ) {
                throw new RuntimeException('Описание таблицы в манифесте некорректно.');
            }

            $normalizedColumns = [];
            foreach ($columns as $column) {
                if (!is_string($column)) {
                    throw new RuntimeException('Манифест содержит некорректное имя столбца.');
                }

                $this->assertIdentifier($column);
                $normalizedColumns[] = $column;
            }

            if ($normalizedColumns === [] || isset($tables[$name])) {
                throw new RuntimeException('Манифест содержит пустую или повторяющуюся таблицу.');
            }

            $tables[$name] = [
                'name' => $name,
                'path' => $path,
                'columns' => $normalizedColumns,
                'rows' => $rows,
            ];
        }

        ksort($tables, SORT_STRING);
        return $tables;
    }

    /**
     * @param array<string,array{name:string,path:string,columns:list<string>,rows:int}> $tables
     */
    private function assertSchemaMatches(PDO $pdo, string $driver, array $tables): void
    {
        $currentTables = $this->tableNames($pdo, $driver);
        $backupTables = array_keys($tables);

        sort($currentTables, SORT_STRING);
        sort($backupTables, SORT_STRING);

        if ($currentTables !== $backupTables) {
            throw new RuntimeException('Схема текущей базы не совпадает со схемой резервной копии.');
        }

        foreach ($tables as $table) {
            $columns = $this->columnNames($pdo, $driver, $table['name']);

            if ($columns !== $table['columns']) {
                throw new RuntimeException(
                    'Столбцы таблицы ' . $table['name'] . ' не совпадают со снимком.'
                );
            }
        }
    }

    /**
     * @param array<string,array{name:string,path:string,columns:list<string>,rows:int}> $tables
     */
    private function restorePostgres(PDO $pdo, string $backupPath, array $tables): int
    {
        $order = $this->postgresInsertOrder($pdo, array_keys($tables));
        $quoted = array_map(
            fn(string $table): string => $this->quoteIdentifier($table, 'pgsql'),
            array_keys($tables),
        );

        $pdo->beginTransaction();

        try {
            if ($quoted !== []) {
                $pdo->exec('TRUNCATE TABLE ' . implode(', ', $quoted) . ' RESTART IDENTITY CASCADE');
            }

            $rows = $this->restoreTables($pdo, 'pgsql', $backupPath, $tables, $order);
            $this->syncPostgresSequences($pdo, $tables);
            $pdo->commit();

            return $rows;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @param array<string,array{name:string,path:string,columns:list<string>,rows:int}> $tables
     */
    private function restoreMysql(PDO $pdo, string $backupPath, array $tables): int
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->beginTransaction();

        try {
            foreach (array_keys($tables) as $table) {
                $pdo->exec('DELETE FROM ' . $this->quoteIdentifier($table, 'mysql'));
            }

            $rows = $this->restoreTables(
                $pdo,
                'mysql',
                $backupPath,
                $tables,
                array_keys($tables),
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->syncMysqlAutoIncrement($pdo, $tables);
        return $rows;
    }

    /**
     * @param array<string,array{name:string,path:string,columns:list<string>,rows:int}> $tables
     * @param list<string> $order
     */
    private function restoreTables(
        PDO $pdo,
        string $driver,
        string $backupPath,
        array $tables,
        array $order,
    ): int {
        $rows = 0;

        foreach ($order as $tableName) {
            $table = $tables[$tableName] ?? null;
            if ($table === null) {
                throw new RuntimeException('Порядок восстановления содержит неизвестную таблицу.');
            }

            $rows += $this->restoreTable($pdo, $driver, $backupPath, $table);
        }

        return $rows;
    }

    /**
     * @param array{name:string,path:string,columns:list<string>,rows:int} $table
     */
    private function restoreTable(
        PDO $pdo,
        string $driver,
        string $backupPath,
        array $table,
    ): int {
        $quotedColumns = array_map(
            fn(string $column): string => $this->quoteIdentifier($column, $driver),
            $table['columns'],
        );
        $placeholders = implode(', ', array_fill(0, count($quotedColumns), '?'));

        $statement = $pdo->prepare(
            'INSERT INTO ' . $this->quoteIdentifier($table['name'], $driver)
            . ' (' . implode(', ', $quotedColumns) . ')'
            . ' VALUES (' . $placeholders . ')'
        );

        $path = $backupPath
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $table['path']);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть дамп таблицы ' . $table['name'] . '.');
        }

        $rows = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $values = $this->decodeRow($line, count($table['columns']));
                $statement->execute($values);
                $rows++;
            }
        } finally {
            fclose($handle);
        }

        if ($rows !== $table['rows']) {
            throw new RuntimeException('Количество строк в дампе таблицы не совпало с манифестом.');
        }

        return $rows;
    }

    /**
     * @return list<?string>
     */
    private function decodeRow(string $line, int $expectedColumns): array
    {
        $encoded = json_decode(rtrim($line, "\r\n"), true, 16, JSON_THROW_ON_ERROR);

        if (!is_array($encoded) || count($encoded) !== $expectedColumns) {
            throw new RuntimeException('Строка дампа базы данных повреждена.');
        }

        $values = [];

        foreach ($encoded as $value) {
            if ($value === null) {
                $values[] = null;
                continue;
            }

            if (!is_string($value)) {
                throw new RuntimeException('Значение в дампе базы данных повреждено.');
            }

            $decoded = base64_decode($value, true);
            if (!is_string($decoded)) {
                throw new RuntimeException('Не удалось декодировать значение из дампа базы данных.');
            }

            $values[] = $decoded;
        }

        return $values;
    }

    /**
     * @param list<string> $tables
     * @return list<string>
     */
    private function postgresInsertOrder(PDO $pdo, array $tables): array
    {
        $known = array_fill_keys($tables, true);
        $dependencies = array_fill_keys($tables, []);
        $children = array_fill_keys($tables, []);

        $sql = <<<'SQL'
SELECT tc.table_name AS child_table, ccu.table_name AS parent_table
FROM information_schema.table_constraints AS tc
JOIN information_schema.constraint_column_usage AS ccu
  ON ccu.constraint_name = tc.constraint_name
 AND ccu.constraint_schema = tc.constraint_schema
WHERE tc.constraint_type = 'FOREIGN KEY'
  AND tc.table_schema = 'public'
  AND ccu.table_schema = 'public'
SQL;

        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $child = (string) ($row['child_table'] ?? '');
            $parent = (string) ($row['parent_table'] ?? '');

            if ($child === $parent || !isset($known[$child], $known[$parent])) {
                continue;
            }

            $dependencies[$child][$parent] = true;
            $children[$parent][$child] = true;
        }

        $ready = [];
        foreach ($tables as $table) {
            if ($dependencies[$table] === []) {
                $ready[] = $table;
            }
        }
        sort($ready, SORT_STRING);

        $order = [];

        while ($ready !== []) {
            $table = array_shift($ready);
            $order[] = $table;

            foreach (array_keys($children[$table]) as $child) {
                unset($dependencies[$child][$table]);

                if ($dependencies[$child] === [] && !in_array($child, $order, true)) {
                    $ready[] = $child;
                }
            }

            $ready = array_values(array_unique($ready));
            sort($ready, SORT_STRING);
        }

        if (count($order) !== count($tables)) {
            throw new RuntimeException(
                'Обнаружен циклический набор внешних ключей, автоматическое восстановление остановлено.'
            );
        }

        return $order;
    }

    /**
     * @param array<string,array{name:string,path:string,columns:list<string>,rows:int}> $tables
     */
    private function syncPostgresSequences(PDO $pdo, array $tables): void
    {
        $sequenceStatement = $pdo->prepare(
            'SELECT pg_get_serial_sequence(:table_name, :column_name)'
        );
        $setStatement = $pdo->prepare(
            'SELECT setval(CAST(:sequence_name AS regclass), :sequence_value, :is_called)'
        );

        foreach ($tables as $table) {
            foreach ($table['columns'] as $column) {
                $sequenceStatement->execute([
                    'table_name' => $table['name'],
                    'column_name' => $column,
                ]);
                $sequence = $sequenceStatement->fetchColumn();

                if (!is_string($sequence) || $sequence === '') {
                    continue;
                }

                $max = $pdo->query(
                    'SELECT MAX(' . $this->quoteIdentifier($column, 'pgsql') . ')'
                    . ' FROM ' . $this->quoteIdentifier($table['name'], 'pgsql')
                )->fetchColumn();

                $hasRows = $max !== false && $max !== null;
                $setStatement->bindValue(':sequence_name', $sequence, PDO::PARAM_STR);
                $setStatement->bindValue(
                    ':sequence_value',
                    $hasRows ? max(1, (int) $max) : 1,
                    PDO::PARAM_INT,
                );
                $setStatement->bindValue(':is_called', $hasRows, PDO::PARAM_BOOL);
                $setStatement->execute();
            }
        }
    }

    /**
     * @param array<string,array{name:string,path:string,columns:list<string>,rows:int}> $tables
     */
    private function syncMysqlAutoIncrement(PDO $pdo, array $tables): void
    {
        $statement = $pdo->prepare(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND extra LIKE '%auto_increment%'"
        );

        foreach ($tables as $table) {
            $statement->execute(['table_name' => $table['name']]);
            $column = $statement->fetchColumn();

            if (!is_string($column) || $column === '') {
                continue;
            }

            $this->assertIdentifier($column);
            $max = $pdo->query(
                'SELECT MAX(' . $this->quoteIdentifier($column, 'mysql') . ')'
                . ' FROM ' . $this->quoteIdentifier($table['name'], 'mysql')
            )->fetchColumn();
            $next = $max === false || $max === null ? 1 : ((int) $max + 1);

            $pdo->exec(
                'ALTER TABLE ' . $this->quoteIdentifier($table['name'], 'mysql')
                . ' AUTO_INCREMENT = ' . max(1, $next)
            );
        }
    }

    /**
     * @param array<string,array{name:string,path:string,columns:list<string>,rows:int}> $tables
     */
    private function verifyRestoredRows(PDO $pdo, string $driver, array $tables): void
    {
        foreach ($tables as $table) {
            $count = $pdo->query(
                'SELECT COUNT(*) FROM ' . $this->quoteIdentifier($table['name'], $driver)
            )->fetchColumn();

            if ((int) $count !== $table['rows']) {
                throw new RuntimeException(
                    'Проверка восстановленной таблицы ' . $table['name'] . ' не пройдена.'
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function tableNames(PDO $pdo, string $driver): array
    {
        $sql = $driver === 'pgsql'
            ? "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name"
            : "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name";

        return array_map(
            static fn(mixed $value): string => (string) $value,
            $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    /**
     * @return list<string>
     */
    private function columnNames(PDO $pdo, string $driver, string $table): array
    {
        $sql = $driver === 'pgsql'
            ? "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = :table ORDER BY ordinal_position"
            : "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ordinal_position";

        $statement = $pdo->prepare($sql);
        $statement->execute(['table' => $table]);

        return array_map(
            static fn(mixed $value): string => (string) $value,
            $statement->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    private function safeDatabasePath(string $path, string $table): bool
    {
        return $path === 'database/' . $table . '.ndjson';
    }

    private function quoteIdentifier(string $identifier, string $driver): string
    {
        $this->assertIdentifier($identifier);

        return $driver === 'mysql'
            ? "\x60" . $identifier . "\x60"
            : '"' . $identifier . '"';
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier) !== 1) {
            throw new RuntimeException('База данных содержит неподдерживаемый идентификатор.');
        }
    }
}
