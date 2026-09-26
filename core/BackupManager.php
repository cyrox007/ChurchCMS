<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use DirectoryIterator;
use PDO;
use RuntimeException;
use Throwable;

final class BackupManager
{
    private const FORMAT = 'churchcms-backup-v1';
    private const BACKUP_ID_PATTERN = '/^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$/D';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly string $root,
        private readonly string $backupRoot,
    ) {
    }

    /**
     * Создаёт проверенную резервную копию конфигурации, загрузок и данных БД.
     *
     * @return array{id: string, files: int, tables: int}
     */
    public function create(): array
    {
        $backupRoot = $this->prepareBackupRoot();
        $id = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6));
        $temporary = $backupRoot . DIRECTORY_SEPARATOR . '.creating-' . $id;
        $final = $backupRoot . DIRECTORY_SEPARATOR . $id;

        if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) {
            throw new RuntimeException('Не удалось создать временный каталог резервной копии.');
        }

        try {
            $files = [];
            $this->copyRequiredFile(
                $this->root . '/config/local.php',
                $temporary . '/config/local.php',
                'config/local.php',
                $files,
            );

            $uploads = $this->root . '/storage/uploads';
            if (is_dir($uploads)) {
                $this->copyDirectory(
                    $uploads,
                    $temporary . '/storage/uploads',
                    'storage/uploads',
                    $files,
                );
            }

            $database = $this->dumpDatabase($temporary . '/database');

            $manifest = [
                'format' => self::FORMAT,
                'created_at' => gmdate(DATE_ATOM),
                'app_version' => (string) Config::get('app.version', ''),
                'php_min' => (string) Config::get('app.php_min', '8.3'),
                'files' => $files,
                'database' => $database,
            ];

            $this->writeJson($temporary . '/manifest.json', $manifest);
            $this->verifyPath($temporary);

            if (!rename($temporary, $final)) {
                throw new RuntimeException('Не удалось зафиксировать готовую резервную копию.');
            }

            return [
                'id' => $id,
                'files' => count($files),
                'tables' => count($database['tables']),
            ];
        } catch (Throwable $e) {
            if (is_dir($temporary)) {
                $this->deleteTree($temporary);
            }

            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException('Не удалось создать резервную копию.', 0, $e);
        }
    }

    public function verify(string $backupId): bool
    {
        if (preg_match(self::BACKUP_ID_PATTERN, $backupId) !== 1) {
            throw new RuntimeException('Некорректный идентификатор резервной копии.');
        }

        $root = $this->prepareBackupRoot();
        $path = $root . DIRECTORY_SEPARATOR . $backupId;

        if (!is_dir($path) || is_link($path)) {
            throw new RuntimeException('Резервная копия не найдена.');
        }

        $this->verifyPath($path);
        return true;
    }

    private function prepareBackupRoot(): string
    {
        if (!$this->isAbsolutePath($this->backupRoot)) {
            throw new RuntimeException('Каталог резервных копий должен быть задан абсолютным путём.');
        }

        $applicationRoot = realpath($this->root);
        if ($applicationRoot === false || !is_dir($applicationRoot)) {
            throw new RuntimeException('Корневой каталог ChurchCMS недоступен.');
        }

        if ($this->pathInside($this->backupRoot, $applicationRoot)) {
            throw new RuntimeException('Резервные копии нельзя хранить по пути внутри публичного корня ChurchCMS.');
        }

        if (is_link($this->backupRoot)) {
            throw new RuntimeException('Каталог резервных копий не должен быть символической ссылкой.');
        }

        if (!is_dir($this->backupRoot) && !mkdir($this->backupRoot, 0700, true) && !is_dir($this->backupRoot)) {
            throw new RuntimeException('Не удалось создать каталог резервных копий.');
        }

        @chmod($this->backupRoot, 0700);

        $resolved = realpath($this->backupRoot);
        if ($resolved === false || is_link($this->backupRoot) || !is_writable($resolved)) {
            throw new RuntimeException('Каталог резервных копий недоступен для безопасной записи.');
        }

        if ($this->pathInside($resolved, $applicationRoot)) {
            throw new RuntimeException('Резервные копии нельзя хранить внутри публичного корня ChurchCMS.');
        }

        return $resolved;
    }

    /**
     * @param list<array{path: string, bytes: int, sha256: string}> $files
     */
    private function copyRequiredFile(string $source, string $target, string $relative, array &$files): void
    {
        if (!is_file($source) || is_link($source)) {
            throw new RuntimeException('Локальная конфигурация отсутствует или небезопасна.');
        }

        $this->copyFile($source, $target, $relative, $files);
    }

    /**
     * @param list<array{path: string, bytes: int, sha256: string}> $files
     */
    private function copyDirectory(string $source, string $target, string $relative, array &$files): void
    {
        if (is_link($source)) {
            throw new RuntimeException('Символические ссылки в данных для резервного копирования запрещены.');
        }

        if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) {
            throw new RuntimeException('Не удалось подготовить каталог внутри резервной копии.');
        }

        foreach (new DirectoryIterator($source) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            if ($entry->isLink()) {
                throw new RuntimeException('Символические ссылки в данных для резервного копирования запрещены.');
            }

            $name = $entry->getFilename();
            if ($name === '' || str_contains($name, '\\')) {
                throw new RuntimeException('Обнаружено неподдерживаемое имя файла в данных сайта.');
            }

            $childRelative = $relative . '/' . $name;
            $childTarget = $target . DIRECTORY_SEPARATOR . $name;

            if ($entry->isDir()) {
                $this->copyDirectory($entry->getPathname(), $childTarget, $childRelative, $files);
                continue;
            }

            if (!$entry->isFile()) {
                throw new RuntimeException('Обнаружен неподдерживаемый тип файла в данных сайта.');
            }

            $this->copyFile($entry->getPathname(), $childTarget, $childRelative, $files);
        }
    }

    /**
     * @param list<array{path: string, bytes: int, sha256: string}> $files
     */
    private function copyFile(string $source, string $target, string $relative, array &$files): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Не удалось подготовить каталог резервной копии.');
        }

        if (!copy($source, $target)) {
            throw new RuntimeException('Не удалось скопировать файл в резервную копию.');
        }

        @chmod($target, 0600);

        $size = filesize($target);
        $hash = hash_file('sha256', $target);
        if ($size === false || !is_string($hash)) {
            throw new RuntimeException('Не удалось вычислить контрольную сумму резервной копии.');
        }

        $files[] = [
            'path' => $relative,
            'bytes' => $size,
            'sha256' => $hash,
        ];
    }

    /**
     * @return array{driver: string, encoding: string, tables: list<array{name: string, path: string, columns: list<string>, rows: int, bytes: int, sha256: string}>}
     */
    private function dumpDatabase(string $target): array
    {
        if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) {
            throw new RuntimeException('Не удалось подготовить каталог дампа базы данных.');
        }

        $pdo = $this->database->connection();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new RuntimeException('Резервное копирование этой базы данных не поддерживается.');
        }

        $pdo->beginTransaction();

        try {
            if ($driver === 'pgsql') {
                $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }

            $tables = [];
            foreach ($this->tableNames($pdo, $driver) as $table) {
                $columns = $this->columnNames($pdo, $driver, $table);
                if ($columns === []) {
                    throw new RuntimeException('Не удалось определить структуру таблицы для резервной копии.');
                }

                $relative = 'database/' . $table . '.ndjson';
                $path = $target . DIRECTORY_SEPARATOR . $table . '.ndjson';
                $rows = $this->dumpTable($pdo, $driver, $table, $columns, $path);
                $size = filesize($path);
                $hash = hash_file('sha256', $path);

                if ($size === false || !is_string($hash)) {
                    throw new RuntimeException('Не удалось проверить дамп таблицы.');
                }

                $tables[] = [
                    'name' => $table,
                    'path' => $relative,
                    'columns' => $columns,
                    'rows' => $rows,
                    'bytes' => $size,
                    'sha256' => $hash,
                ];
            }

            $pdo->commit();

            return [
                'driver' => $driver,
                'encoding' => 'base64-or-null',
                'tables' => $tables,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
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

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $tables = [];

        foreach ($rows as $row) {
            $table = (string) $row;
            $this->assertIdentifier($table);
            $tables[] = $table;
        }

        return $tables;
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

        $columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $row) {
            $column = (string) $row;
            $this->assertIdentifier($column);
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @param list<string> $columns
     */
    private function dumpTable(PDO $pdo, string $driver, string $table, array $columns, string $path): int
    {
        $quotedColumns = implode(
            ', ',
            array_map(fn(string $column): string => $this->quoteIdentifier($column, $driver), $columns),
        );
        $sql = 'SELECT ' . $quotedColumns . ' FROM ' . $this->quoteIdentifier($table, $driver);
        $statement = $pdo->query($sql);

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Не удалось создать файл дампа таблицы.');
        }

        @chmod($path, 0600);
        $rows = 0;

        try {
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                $encoded = [];

                foreach ($columns as $column) {
                    $value = $row[$column] ?? null;
                    $encoded[] = $this->encodeDatabaseValue($value);
                }

                $line = json_encode(
                    $encoded,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                ) . "\n";

                $this->writeStream($handle, $line);
                $rows++;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function encodeDatabaseValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_resource($value)) {
            $bytes = stream_get_contents($value);
            if (!is_string($bytes)) {
                throw new RuntimeException('Не удалось прочитать бинарное значение из базы данных.');
            }

            return base64_encode($bytes);
        }

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            throw new RuntimeException('База данных вернула неподдерживаемый тип значения.');
        }

        return base64_encode((string) $value);
    }

    private function quoteIdentifier(string $identifier, string $driver): string
    {
        $this->assertIdentifier($identifier);
        return $driver === 'mysql'
            ? '`' . $identifier . '`'
            : '"' . $identifier . '"';
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier) !== 1) {
            throw new RuntimeException('База данных содержит неподдерживаемый идентификатор.');
        }
    }

    /**
     * @param resource $handle
     */
    private function writeStream($handle, string $data): void
    {
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $written = fwrite($handle, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Не удалось записать данные резервной копии.');
            }
            $offset += $written;
        }
    }

    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) . "\n";

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Не удалось записать манифест резервной копии.');
        }

        @chmod($path, 0600);
    }

    private function verifyPath(string $path): void
    {
        $manifestPath = $path . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($manifestPath) || is_link($manifestPath)) {
            throw new RuntimeException('Манифест резервной копии отсутствует или небезопасен.');
        }

        $raw = file_get_contents($manifestPath);
        if (!is_string($raw)) {
            throw new RuntimeException('Не удалось прочитать манифест резервной копии.');
        }

        $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Формат резервной копии не поддерживается.');
        }

        foreach (($manifest['files'] ?? []) as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Манифест резервной копии повреждён.');
            }
            $this->verifyFileEntry($path, $entry);
        }

        $database = $manifest['database'] ?? null;
        if (!is_array($database) || !is_array($database['tables'] ?? null)) {
            throw new RuntimeException('В резервной копии отсутствует описание базы данных.');
        }

        foreach ($database['tables'] as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Манифест базы данных повреждён.');
            }
            $this->verifyFileEntry($path, $entry);
        }
    }

    private function verifyFileEntry(string $root, array $entry): void
    {
        $relative = (string) ($entry['path'] ?? '');
        $expectedHash = (string) ($entry['sha256'] ?? '');
        $expectedBytes = $entry['bytes'] ?? null;

        if (!$this->safeRelativePath($relative) || preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1) {
            throw new RuntimeException('Манифест содержит небезопасный путь или контрольную сумму.');
        }

        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Файл резервной копии отсутствует или небезопасен.');
        }

        $actualHash = hash_file('sha256', $path);
        $actualBytes = filesize($path);

        if (!is_string($actualHash) || $actualHash !== $expectedHash || $actualBytes !== $expectedBytes) {
            throw new RuntimeException('Контрольная сумма резервной копии не совпала.');
        }
    }

    private function safeRelativePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_starts_with($path, '\\') || str_contains($path, "\0")) {
            return false;
        }

        if (str_contains($path, '\\')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/D', $path) === 1
            || str_starts_with($path, '\\\\');
    }

    private function pathInside(string $candidate, string $root): bool
    {
        $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
        $root = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root);
        $candidate = rtrim($candidate, DIRECTORY_SEPARATOR);
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        if (DIRECTORY_SEPARATOR === '\\') {
            $candidate = strtolower($candidate);
            $root = strtolower($root);
        }

        return $candidate === $root
            || str_starts_with($candidate, $root . DIRECTORY_SEPARATOR);
    }

    private function deleteTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (new DirectoryIterator($path) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            $this->deleteTree($entry->getPathname());
        }

        @rmdir($path);
    }
}
