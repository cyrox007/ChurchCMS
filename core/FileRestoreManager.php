<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use DirectoryIterator;
use RuntimeException;
use Throwable;

final class FileRestoreManager
{
    private const BACKUP_ID_PATTERN = '/^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$/D';
    private const UPLOAD_PREFIX = 'storage/uploads/';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly string $root,
        private readonly string $backupRoot,
    ) {
    }

    /**
     * Восстанавливает локальную конфигурацию и пользовательские загрузки
     * из уже проверенной резервной копии.
     *
     * @return array{files:int}
     */
    public function restore(string $backupId): array
    {
        $backupPath = $this->verifiedBackupPath($backupId);
        [$config, $uploads] = $this->manifestFiles($backupPath);

        $token = bin2hex(random_bytes(6));
        $configCandidate = $this->prepareConfigCandidate(
            $backupPath,
            $config,
            $token,
        );

        try {
            $uploadsCandidate = $this->prepareUploadsCandidate(
                $backupPath,
                $uploads,
                $token,
            );
        } catch (Throwable $e) {
            @unlink($configCandidate);
            throw $e;
        }

        try {
            $this->swapIntoPlace(
                $configCandidate,
                $uploadsCandidate,
                $token,
            );
        } catch (Throwable $e) {
            if (is_file($configCandidate)) {
                @unlink($configCandidate);
            }
            if (is_dir($uploadsCandidate)) {
                $this->deleteTree($uploadsCandidate);
            }

            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException(
                'Не удалось восстановить файловую часть резервной копии.',
                0,
                $e,
            );
        }

        return [
            'files' => 1 + count($uploads),
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

        $backupRoot = realpath($this->backupRoot);
        if ($backupRoot === false || !is_dir($backupRoot) || is_link($backupRoot)) {
            throw new RuntimeException('Каталог резервных копий недоступен.');
        }

        $backupPath = realpath($backupRoot . DIRECTORY_SEPARATOR . $backupId);
        if (
            $backupPath === false
            || !is_dir($backupPath)
            || is_link($backupPath)
            || dirname($backupPath) !== $backupRoot
        ) {
            throw new RuntimeException('Не удалось открыть проверенную резервную копию.');
        }

        return $backupPath;
    }

    /**
     * @return array{
     *     0:array{path:string,bytes:int,sha256:string},
     *     1:list<array{path:string,bytes:int,sha256:string}>
     * }
     */
    private function manifestFiles(string $backupPath): array
    {
        $raw = file_get_contents($backupPath . DIRECTORY_SEPARATOR . 'manifest.json');
        if (!is_string($raw)) {
            throw new RuntimeException('Не удалось прочитать манифест резервной копии.');
        }

        $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        $files = is_array($manifest) ? ($manifest['files'] ?? null) : null;

        if (!is_array($files)) {
            throw new RuntimeException('В манифесте отсутствует список файлов.');
        }

        $config = null;
        $uploads = [];
        $seen = [];

        foreach ($files as $entry) {
            $normalized = $this->normalizeFileEntry($entry);
            $path = $normalized['path'];

            if (isset($seen[$path])) {
                throw new RuntimeException('Манифест содержит повторяющийся путь файла.');
            }
            $seen[$path] = true;

            if ($path === 'config/local.php') {
                $config = $normalized;
                continue;
            }

            if (!str_starts_with($path, self::UPLOAD_PREFIX)) {
                throw new RuntimeException(
                    'Манифест содержит файл вне разрешённой области восстановления.'
                );
            }

            $relative = substr($path, strlen(self::UPLOAD_PREFIX));
            if (!$this->safeRelativePath($relative)) {
                throw new RuntimeException('Манифест содержит небезопасный путь загрузки.');
            }

            $uploads[] = $normalized;
        }

        if ($config === null) {
            throw new RuntimeException('В резервной копии отсутствует config/local.php.');
        }

        usort(
            $uploads,
            static fn(array $a, array $b): int => strcmp($a['path'], $b['path']),
        );

        return [$config, $uploads];
    }

    /**
     * @return array{path:string,bytes:int,sha256:string}
     */
    private function normalizeFileEntry(mixed $entry): array
    {
        if (!is_array($entry)) {
            throw new RuntimeException('Описание файла в манифесте повреждено.');
        }

        $path = $entry['path'] ?? null;
        $bytes = $entry['bytes'] ?? null;
        $sha256 = $entry['sha256'] ?? null;

        if (
            !is_string($path)
            || !is_int($bytes)
            || $bytes < 0
            || !is_string($sha256)
            || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
        ) {
            throw new RuntimeException('Описание файла в манифесте некорректно.');
        }

        return [
            'path' => $path,
            'bytes' => $bytes,
            'sha256' => $sha256,
        ];
    }

    /**
     * @param array{path:string,bytes:int,sha256:string} $entry
     */
    private function prepareConfigCandidate(
        string $backupPath,
        array $entry,
        string $token,
    ): string {
        $configDirectory = $this->applicationDirectory('config');
        $candidate = $configDirectory
            . DIRECTORY_SEPARATOR
            . '.local.php.restore-new-'
            . $token;

        $source = $backupPath
            . DIRECTORY_SEPARATOR
            . 'config'
            . DIRECTORY_SEPARATOR
            . 'local.php';

        $this->copyVerified($source, $candidate, $entry);
        return $candidate;
    }

    /**
     * @param list<array{path:string,bytes:int,sha256:string}> $entries
     */
    private function prepareUploadsCandidate(
        string $backupPath,
        array $entries,
        string $token,
    ): string {
        $storage = $this->applicationDirectory('storage');
        $candidate = $storage
            . DIRECTORY_SEPARATOR
            . '.uploads.restore-new-'
            . $token;

        if (file_exists($candidate) || is_link($candidate)) {
            throw new RuntimeException('Временный каталог восстановления уже существует.');
        }

        if (!mkdir($candidate, 0700)) {
            throw new RuntimeException('Не удалось подготовить временный каталог загрузок.');
        }

        try {
            foreach ($entries as $entry) {
                $relative = substr($entry['path'], strlen(self::UPLOAD_PREFIX));
                $source = $backupPath
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
                $target = $candidate
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $relative);

                $this->copyVerified($source, $target, $entry);
            }
        } catch (Throwable $e) {
            $this->deleteTree($candidate);
            throw $e;
        }

        return $candidate;
    }

    private function swapIntoPlace(
        string $configCandidate,
        string $uploadsCandidate,
        string $token,
    ): void {
        $configDirectory = $this->applicationDirectory('config');
        $storage = $this->applicationDirectory('storage');

        $configTarget = $configDirectory . DIRECTORY_SEPARATOR . 'local.php';
        $configRollback = $configDirectory
            . DIRECTORY_SEPARATOR
            . '.local.php.restore-old-'
            . $token;
        $uploadsTarget = $storage . DIRECTORY_SEPARATOR . 'uploads';
        $uploadsRollback = $storage
            . DIRECTORY_SEPARATOR
            . '.uploads.restore-old-'
            . $token;

        $this->assertCurrentTargets($configTarget, $uploadsTarget);

        $state = [
            'config_moved' => false,
            'config_installed' => false,
            'uploads_moved' => false,
            'uploads_installed' => false,
        ];

        try {
            if (!rename($configTarget, $configRollback)) {
                throw new RuntimeException('Не удалось сохранить текущую локальную конфигурацию для отката.');
            }
            $state['config_moved'] = true;

            if (!rename($configCandidate, $configTarget)) {
                throw new RuntimeException('Не удалось установить восстановленную локальную конфигурацию.');
            }
            $state['config_installed'] = true;

            if (is_dir($uploadsTarget)) {
                if (!rename($uploadsTarget, $uploadsRollback)) {
                    throw new RuntimeException('Не удалось сохранить текущие загрузки для отката.');
                }
                $state['uploads_moved'] = true;
            }

            if (!rename($uploadsCandidate, $uploadsTarget)) {
                throw new RuntimeException('Не удалось установить восстановленные загрузки.');
            }
            $state['uploads_installed'] = true;
        } catch (Throwable $e) {
            $rolledBack = $this->rollbackSwap(
                $configTarget,
                $configRollback,
                $uploadsTarget,
                $uploadsRollback,
                $state,
            );

            if (!$rolledBack) {
                throw new RuntimeException(
                    'Восстановление файлов прервано, а автоматический откат завершился не полностью. Требуется ручная проверка.',
                    0,
                    $e,
                );
            }

            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException('Не удалось применить восстановленные файлы.', 0, $e);
        }

        @unlink($configRollback);
        if (is_dir($uploadsRollback)) {
            $this->deleteTree($uploadsRollback);
        }
    }

    private function assertCurrentTargets(
        string $configTarget,
        string $uploadsTarget,
    ): void {
        if (!is_file($configTarget) || is_link($configTarget)) {
            throw new RuntimeException('Текущий config/local.php отсутствует или небезопасен.');
        }

        if (file_exists($uploadsTarget) && (!is_dir($uploadsTarget) || is_link($uploadsTarget))) {
            throw new RuntimeException('Текущий storage/uploads имеет неподдерживаемый тип.');
        }
    }

    /**
     * @param array{
     *     config_moved:bool,
     *     config_installed:bool,
     *     uploads_moved:bool,
     *     uploads_installed:bool
     * } $state
     */
    private function rollbackSwap(
        string $configTarget,
        string $configRollback,
        string $uploadsTarget,
        string $uploadsRollback,
        array $state,
    ): bool {
        $ok = true;

        if ($state['uploads_installed'] && is_dir($uploadsTarget)) {
            $this->deleteTree($uploadsTarget);
        }

        if ($state['uploads_moved'] && is_dir($uploadsRollback)) {
            if (!rename($uploadsRollback, $uploadsTarget)) {
                $ok = false;
            }
        }

        if ($state['config_installed'] && is_file($configTarget)) {
            if (!unlink($configTarget)) {
                $ok = false;
            }
        }

        if ($state['config_moved'] && is_file($configRollback)) {
            if (!rename($configRollback, $configTarget)) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * @param array{path:string,bytes:int,sha256:string} $entry
     */
    private function copyVerified(
        string $source,
        string $target,
        array $entry,
    ): void {
        if (!is_file($source) || is_link($source)) {
            throw new RuntimeException('Файл резервной копии отсутствует или небезопасен.');
        }

        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Не удалось подготовить каталог для восстановления файла.');
        }

        if (!copy($source, $target)) {
            throw new RuntimeException('Не удалось скопировать файл из резервной копии.');
        }

        @chmod($target, 0600);

        $bytes = filesize($target);
        $sha256 = hash_file('sha256', $target);

        if (
            $bytes !== $entry['bytes']
            || !is_string($sha256)
            || !hash_equals($entry['sha256'], $sha256)
        ) {
            @unlink($target);
            throw new RuntimeException('Проверка восстановленного файла по SHA-256 не пройдена.');
        }
    }

    private function applicationDirectory(string $relative): string
    {
        $path = rtrim($this->root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $relative;

        if (!is_dir($path) || is_link($path)) {
            throw new RuntimeException('Каталог ChurchCMS для восстановления недоступен.');
        }

        $resolved = realpath($path);
        if ($resolved === false) {
            throw new RuntimeException('Не удалось определить каталог ChurchCMS для восстановления.');
        }

        return $resolved;
    }

    private function safeRelativePath(string $path): bool
    {
        if (
            $path === ''
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || str_contains($path, "\0")
            || str_contains($path, '\\')
        ) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
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
