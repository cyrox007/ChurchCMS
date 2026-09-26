<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use DirectoryIterator;
use RuntimeException;
use Throwable;

final class FilesystemRestoreManager
{
    private const BACKUP_ID_PATTERN = '/^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$/D';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly string $root,
        private readonly string $backupRoot,
    ) {
    }

    /**
     * Восстанавливает config/local.php и storage/uploads из проверенной копии.
     *
     * @return array{files:int}
     */
    public function restore(string $backupId): array
    {
        $backupPath = $this->verifiedBackupPath($backupId);
        $files = $this->manifestFiles($backupPath);
        $token = bin2hex(random_bytes(8));

        $configPath = $this->root . '/config/local.php';
        $uploadsPath = $this->root . '/storage/uploads';
        $configStage = $this->root . '/config/.restore-local-' . $token . '.php';
        $uploadsStage = $this->root . '/storage/.restore-uploads-' . $token;
        $configRollback = $this->root . '/config/.rollback-local-' . $token . '.php';
        $uploadsRollback = $this->root . '/storage/.rollback-uploads-' . $token;

        $this->assertCurrentTargets($configPath, $uploadsPath);
        $this->assertTemporaryPathsFree([
            $configStage,
            $uploadsStage,
            $configRollback,
            $uploadsRollback,
        ]);

        $this->prepareStage(
            $backupPath,
            $files,
            $configStage,
            $uploadsStage,
        );

        $configMoved = false;
        $uploadsMoved = false;
        $configInstalled = false;
        $uploadsInstalled = false;

        try {
            if (!rename($configPath, $configRollback)) {
                throw new RuntimeException('Не удалось подготовить откат локальной конфигурации.');
            }
            $configMoved = true;

            if (!rename($configStage, $configPath)) {
                throw new RuntimeException('Не удалось установить восстановленную конфигурацию.');
            }
            $configInstalled = true;

            if (!rename($uploadsPath, $uploadsRollback)) {
                throw new RuntimeException('Не удалось подготовить откат пользовательских файлов.');
            }
            $uploadsMoved = true;

            if (!rename($uploadsStage, $uploadsPath)) {
                throw new RuntimeException('Не удалось установить восстановленные пользовательские файлы.');
            }
            $uploadsInstalled = true;

            $this->verifyInstalledFiles($files, $configPath, $uploadsPath);

            @unlink($configRollback);
            $this->deleteTree($uploadsRollback);

            return ['files' => count($files)];
        } catch (Throwable $e) {
            $this->rollback(
                $configPath,
                $uploadsPath,
                $configRollback,
                $uploadsRollback,
                $configStage,
                $uploadsStage,
                $configMoved,
                $uploadsMoved,
                $configInstalled,
                $uploadsInstalled,
            );

            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException('Не удалось восстановить файлы из резервной копии.', 0, $e);
        }
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
     * @return array<string,array{path:string,bytes:int,sha256:string}>
     */
    private function manifestFiles(string $backupPath): array
    {
        $raw = file_get_contents($backupPath . '/manifest.json');
        if (!is_string($raw)) {
            throw new RuntimeException('Не удалось прочитать манифест резервной копии.');
        }

        $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        $entries = is_array($manifest) ? ($manifest['files'] ?? null) : null;

        if (!is_array($entries)) {
            throw new RuntimeException('В манифесте отсутствует список файлов.');
        }

        $files = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Описание файла в манифесте повреждено.');
            }

            $path = (string) ($entry['path'] ?? '');
            $bytes = $entry['bytes'] ?? null;
            $sha256 = (string) ($entry['sha256'] ?? '');

            if (
                !$this->allowedPath($path)
                || !is_int($bytes)
                || $bytes < 0
                || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
                || isset($files[$path])
            ) {
                throw new RuntimeException('Манифест содержит некорректное описание файла.');
            }

            $files[$path] = [
                'path' => $path,
                'bytes' => $bytes,
                'sha256' => $sha256,
            ];
        }

        if (!isset($files['config/local.php'])) {
            throw new RuntimeException('В резервной копии отсутствует локальная конфигурация.');
        }

        ksort($files, SORT_STRING);
        return $files;
    }

    private function allowedPath(string $path): bool
    {
        if ($path === 'config/local.php') {
            return true;
        }

        if (!str_starts_with($path, 'storage/uploads/')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return !str_contains($path, "\0")
            && !str_contains($path, '\\');
    }

    private function assertCurrentTargets(string $configPath, string $uploadsPath): void
    {
        if (!is_file($configPath) || is_link($configPath)) {
            throw new RuntimeException('Текущая локальная конфигурация отсутствует или небезопасна.');
        }

        if (!is_dir($uploadsPath) || is_link($uploadsPath)) {
            throw new RuntimeException('Текущий каталог пользовательских файлов отсутствует или небезопасен.');
        }

        if (!is_writable(dirname($configPath)) || !is_writable(dirname($uploadsPath))) {
            throw new RuntimeException('Недостаточно прав для безопасной замены файлов.');
        }
    }

    /**
     * @param list<string> $paths
     */
    private function assertTemporaryPathsFree(array $paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path) || is_link($path)) {
                throw new RuntimeException('Временный путь восстановления уже занят.');
            }
        }
    }

    /**
     * @param array<string,array{path:string,bytes:int,sha256:string}> $files
     */
    private function prepareStage(
        string $backupPath,
        array $files,
        string $configStage,
        string $uploadsStage,
    ): void {
        if (!mkdir($uploadsStage, 0750, true) && !is_dir($uploadsStage)) {
            throw new RuntimeException('Не удалось подготовить временный каталог пользовательских файлов.');
        }

        try {
            foreach ($files as $entry) {
                $source = $backupPath
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);

                if (!is_file($source) || is_link($source)) {
                    throw new RuntimeException('Файл резервной копии отсутствует или небезопасен.');
                }

                if ($entry['path'] === 'config/local.php') {
                    $target = $configStage;
                    $mode = 0600;
                } else {
                    $relative = substr($entry['path'], strlen('storage/uploads/'));
                    $target = $uploadsStage
                        . DIRECTORY_SEPARATOR
                        . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    $mode = 0640;
                }

                $this->copyVerifiedFile($source, $target, $entry, $mode);
            }

            $backupUploads = $backupPath . '/storage/uploads';
            if (!is_dir($backupUploads) || is_link($backupUploads)) {
                throw new RuntimeException('В резервной копии отсутствует безопасный каталог uploads.');
            }
        } catch (Throwable $e) {
            @unlink($configStage);
            $this->deleteTree($uploadsStage);
            throw $e;
        }
    }

    /**
     * @param array{path:string,bytes:int,sha256:string} $entry
     */
    private function copyVerifiedFile(
        string $source,
        string $target,
        array $entry,
        int $mode,
    ): void {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Не удалось подготовить каталог для восстанавливаемого файла.');
        }

        if (!copy($source, $target)) {
            throw new RuntimeException('Не удалось скопировать файл из резервной копии.');
        }

        @chmod($target, $mode);
        $this->assertFileChecksum($target, $entry);
    }

    /**
     * @param array<string,array{path:string,bytes:int,sha256:string}> $files
     */
    private function verifyInstalledFiles(
        array $files,
        string $configPath,
        string $uploadsPath,
    ): void {
        $expectedUploads = [];

        foreach ($files as $entry) {
            if ($entry['path'] === 'config/local.php') {
                $this->assertFileChecksum($configPath, $entry);
                continue;
            }

            $relative = substr($entry['path'], strlen('storage/uploads/'));
            $target = $uploadsPath
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $this->assertFileChecksum($target, $entry);
            $expectedUploads[$relative] = true;
        }

        $actualUploads = $this->uploadFiles($uploadsPath);
        ksort($expectedUploads, SORT_STRING);
        ksort($actualUploads, SORT_STRING);

        if (array_keys($expectedUploads) !== array_keys($actualUploads)) {
            throw new RuntimeException('Набор восстановленных пользовательских файлов не совпал с резервной копией.');
        }
    }

    /**
     * @param array{path:string,bytes:int,sha256:string} $entry
     */
    private function assertFileChecksum(string $path, array $entry): void
    {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Восстановленный файл отсутствует или небезопасен.');
        }

        $bytes = filesize($path);
        $sha256 = hash_file('sha256', $path);

        if ($bytes !== $entry['bytes'] || !is_string($sha256) || $sha256 !== $entry['sha256']) {
            throw new RuntimeException('Контрольная сумма восстановленного файла не совпала.');
        }
    }

    /**
     * @return array<string,true>
     */
    private function uploadFiles(string $root, string $prefix = ''): array
    {
        $files = [];

        foreach (new DirectoryIterator($root) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            if ($entry->isLink()) {
                throw new RuntimeException('Символические ссылки в восстановленных uploads запрещены.');
            }

            $relative = $prefix === ''
                ? $entry->getFilename()
                : $prefix . '/' . $entry->getFilename();

            if ($entry->isDir()) {
                $files += $this->uploadFiles($entry->getPathname(), $relative);
                continue;
            }

            if (!$entry->isFile()) {
                throw new RuntimeException('Обнаружен неподдерживаемый тип файла в uploads.');
            }

            $files[$relative] = true;
        }

        return $files;
    }

    private function rollback(
        string $configPath,
        string $uploadsPath,
        string $configRollback,
        string $uploadsRollback,
        string $configStage,
        string $uploadsStage,
        bool $configMoved,
        bool $uploadsMoved,
        bool $configInstalled,
        bool $uploadsInstalled,
    ): void {
        if ($uploadsInstalled && is_dir($uploadsPath) && !is_link($uploadsPath)) {
            $this->deleteTree($uploadsPath);
        }

        if ($uploadsMoved && is_dir($uploadsRollback) && !is_link($uploadsRollback)) {
            @rename($uploadsRollback, $uploadsPath);
        }

        if ($configInstalled && is_file($configPath) && !is_link($configPath)) {
            @unlink($configPath);
        }

        if ($configMoved && is_file($configRollback) && !is_link($configRollback)) {
            @rename($configRollback, $configPath);
        }

        @unlink($configStage);
        $this->deleteTree($uploadsStage);
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
