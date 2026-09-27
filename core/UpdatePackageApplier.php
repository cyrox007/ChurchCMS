<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use ParseError;
use PhpToken;
use RuntimeException;
use Throwable;

final class UpdatePackageApplier
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly string $root,
        private readonly string $stagingRoot,
        private readonly string $backupRoot,
    ) {
    }

    /**
     * Применяет только code-only пакет без изменений схемы БД.
     *
     * @return array{
     *     id:string,
     *     version:string,
     *     backup_id:string,
     *     files:int,
     *     deleted_files:int
     * }
     */
    public function apply(string $stageId): array
    {
        $stage = (new UpdatePackageStager(
            $this->root,
            $this->stagingRoot,
        ))->inspect($stageId);

        $this->assertCodeOnly($stage);
        $this->assertVersionFilePresent($stage['files']);

        $backup = new BackupManager(
            $this->database,
            $this->root,
            $this->backupRoot,
        );
        $backupResult = $backup->create();
        $backup->verify($backupResult['id']);

        $token = bin2hex(random_bytes(6));
        $operations = [];

        try {
            $operations = $this->prepareOperations($stage, $token);
            $this->applyOperations($operations);
            $this->verifyAppliedState($stage);
            $this->cleanupAfterSuccess($operations);
        } catch (Throwable $e) {
            $rollbackOk = $this->rollbackOperations($operations);
            $this->reloadConfiguration();

            if (!$rollbackOk) {
                throw new RuntimeException(
                    'Обновление прервано, а автоматический откат файлов завершился не полностью. '
                    . 'Требуется ручная проверка установки.',
                    0,
                    $e,
                );
            }

            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException(
                'Не удалось применить пакет обновления. Изменения файлов отменены.',
                0,
                $e,
            );
        }

        return [
            'id' => $stage['id'],
            'version' => $stage['version'],
            'backup_id' => $backupResult['id'],
            'files' => count($stage['files']),
            'deleted_files' => count($stage['deleted_files']),
        ];
    }

    /**
     * @param array{
     *     id:string,
     *     version:string,
     *     path:string,
     *     files:array<string,array{path:string,bytes:int,sha256:string}>,
     *     deleted_files:list<string>
     * } $stage
     */
    private function assertCodeOnly(array $stage): void
    {
        $paths = array_merge(
            array_keys($stage['files']),
            $stage['deleted_files'],
        );

        foreach ($paths as $path) {
            if ($this->isMigrationPath($path)) {
                throw new RuntimeException(
                    'Пакеты с миграциями пока нельзя применять автоматически: '
                    . 'для schema-changing обновлений требуется отдельный безопасный rollback схемы БД.'
                );
            }
        }
    }

    /**
     * @param array<string,array{path:string,bytes:int,sha256:string}> $files
     */
    private function assertVersionFilePresent(array $files): void
    {
        if (!isset($files['config/app.php'])) {
            throw new RuntimeException(
                'Применяемый пакет должен содержать config/app.php с новой версией ChurchCMS.'
            );
        }
    }

    private function isMigrationPath(string $path): bool
    {
        return str_starts_with($path, 'database/migrations/')
            || preg_match('#^modules/[^/]+/migrations/#D', $path) === 1;
    }

    /**
     * @param array{
     *     id:string,
     *     version:string,
     *     path:string,
     *     files:array<string,array{path:string,bytes:int,sha256:string}>,
     *     deleted_files:list<string>
     * } $stage
     * @return list<array{
     *     path:string,
     *     target:string,
     *     candidate:?string,
     *     previous:?string,
     *     delete:bool,
     *     old_moved:bool,
     *     new_installed:bool
     * }>
     */
    private function prepareOperations(array $stage, string $token): array
    {
        $operations = [];

        try {
            foreach ($stage['files'] as $entry) {
                $path = $entry['path'];
                $target = $this->targetPath($path, true);
                $candidate = $target . '.churchcms-new-' . $token;
                $previous = $target . '.churchcms-old-' . $token;

                $this->assertTemporaryPathsFree($candidate, $previous);
                $source = $stage['path']
                    . DIRECTORY_SEPARATOR
                    . 'payload'
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $path);

                $this->copyCandidate($source, $candidate, $entry, $target);

                $operations[] = [
                    'path' => $path,
                    'target' => $target,
                    'candidate' => $candidate,
                    'previous' => $previous,
                    'delete' => false,
                    'old_moved' => false,
                    'new_installed' => false,
                ];
            }

            foreach ($stage['deleted_files'] as $path) {
                $target = $this->targetPath($path, false);
                $previous = $target . '.churchcms-old-' . $token;

                $this->assertTemporaryPathsFree(null, $previous);

                $operations[] = [
                    'path' => $path,
                    'target' => $target,
                    'candidate' => null,
                    'previous' => $previous,
                    'delete' => true,
                    'old_moved' => false,
                    'new_installed' => false,
                ];
            }
        } catch (Throwable $e) {
            $this->cleanupCandidates($operations);
            throw $e;
        }

        return $operations;
    }

    private function assertTemporaryPathsFree(
        ?string $candidate,
        ?string $previous,
    ): void {
        foreach ([$candidate, $previous] as $path) {
            if ($path === null) {
                continue;
            }

            if (file_exists($path) || is_link($path)) {
                throw new RuntimeException(
                    'Обнаружен незавершённый временный файл предыдущего обновления.'
                );
            }
        }
    }

    /**
     * @param array{path:string,bytes:int,sha256:string} $entry
     */
    private function copyCandidate(
        string $source,
        string $candidate,
        array $entry,
        string $target,
    ): void {
        if (!is_file($source) || is_link($source)) {
            throw new RuntimeException('Файл staging-пакета отсутствует или небезопасен.');
        }

        if (!copy($source, $candidate)) {
            throw new RuntimeException('Не удалось подготовить файл обновления рядом с целевым путём.');
        }

        $mode = is_file($target)
            ? (fileperms($target) & 0777)
            : 0644;
        @chmod($candidate, $mode);

        $this->assertFileChecksum($candidate, $entry);
    }

    /**
     * @param list<array{
     *     path:string,target:string,candidate:?string,previous:?string,
     *     delete:bool,old_moved:bool,new_installed:bool
     * }> $operations
     */
    private function applyOperations(array &$operations): void
    {
        foreach ($operations as &$operation) {
            $target = $operation['target'];
            $previous = $operation['previous'];

            if (is_link($target)) {
                throw new RuntimeException(
                    'Целевой файл обновления неожиданно стал символической ссылкой.'
                );
            }

            if (is_file($target)) {
                if ($previous === null || !rename($target, $previous)) {
                    throw new RuntimeException(
                        'Не удалось сохранить текущую версию файла перед заменой.'
                    );
                }
                $operation['old_moved'] = true;
            } elseif (file_exists($target)) {
                throw new RuntimeException(
                    'Целевой путь обновления имеет неподдерживаемый тип.'
                );
            }

            if ($operation['delete']) {
                continue;
            }

            $candidate = $operation['candidate'];
            if ($candidate === null || !is_file($candidate)) {
                throw new RuntimeException('Подготовленный файл обновления отсутствует.');
            }

            if (!rename($candidate, $target)) {
                throw new RuntimeException('Не удалось установить новый файл обновления.');
            }

            $operation['new_installed'] = true;
        }
        unset($operation);
    }

    /**
     * @param array{
     *     id:string,
     *     version:string,
     *     path:string,
     *     files:array<string,array{path:string,bytes:int,sha256:string}>,
     *     deleted_files:list<string>
     * } $stage
     */
    private function verifyAppliedState(array $stage): void
    {
        foreach ($stage['files'] as $entry) {
            $target = $this->targetPath($entry['path'], false);
            $this->assertFileChecksum($target, $entry);

            if (str_ends_with(strtolower($entry['path']), '.php')) {
                $this->assertPhpSyntax($target);
            }
        }

        foreach ($stage['deleted_files'] as $path) {
            $target = $this->targetPath($path, false);
            if (file_exists($target) || is_link($target)) {
                throw new RuntimeException(
                    'Файл, помеченный на удаление, остался в установленной системе.'
                );
            }
        }

        $this->reloadConfiguration();

        if ((string) Config::get('app.version', '') !== $stage['version']) {
            throw new RuntimeException(
                'После применения пакета версия ChurchCMS не совпала с manifest.'
            );
        }

        $health = (new InstallationHealthCheck(
            $this->database,
            $this->root,
        ))->check();

        if (($health['ready'] ?? false) !== true) {
            throw new RuntimeException(
                'Healthcheck после обновления не пройден: '
                . json_encode(
                    $health,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                )
            );
        }
    }

    private function assertPhpSyntax(string $path): void
    {
        $source = file_get_contents($path);
        if (!is_string($source)) {
            throw new RuntimeException('Не удалось прочитать обновлённый PHP-файл.');
        }

        try {
            PhpToken::tokenize($source, TOKEN_PARSE);
        } catch (ParseError $e) {
            throw new RuntimeException(
                'Обновлённый PHP-файл содержит синтаксическую ошибку: '
                . basename($path),
                0,
                $e,
            );
        }
    }

    private function reloadConfiguration(): void
    {
        Config::load(
            rtrim($this->root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'config'
            . DIRECTORY_SEPARATOR
            . 'app.php'
        );
    }

    /**
     * @param list<array{
     *     path:string,target:string,candidate:?string,previous:?string,
     *     delete:bool,old_moved:bool,new_installed:bool
     * }> $operations
     */
    private function rollbackOperations(array &$operations): bool
    {
        $ok = true;

        for ($index = count($operations) - 1; $index >= 0; $index--) {
            $operation = &$operations[$index];
            $target = $operation['target'];
            $previous = $operation['previous'];
            $candidate = $operation['candidate'];

            if ($operation['new_installed'] && is_file($target)) {
                if (!unlink($target)) {
                    $ok = false;
                }
            }

            if (
                $operation['old_moved']
                && $previous !== null
                && is_file($previous)
            ) {
                if (file_exists($target) && !unlink($target)) {
                    $ok = false;
                }

                if (!file_exists($target) && !rename($previous, $target)) {
                    $ok = false;
                }
            }

            if ($candidate !== null && is_file($candidate)) {
                @unlink($candidate);
            }
        }
        unset($operation);

        return $ok;
    }

    /**
     * @param list<array{
     *     path:string,target:string,candidate:?string,previous:?string,
     *     delete:bool,old_moved:bool,new_installed:bool
     * }> $operations
     */
    private function cleanupAfterSuccess(array $operations): void
    {
        foreach ($operations as $operation) {
            $previous = $operation['previous'];
            $candidate = $operation['candidate'];

            if ($previous !== null && is_file($previous)) {
                @unlink($previous);
            }

            if ($candidate !== null && is_file($candidate)) {
                @unlink($candidate);
            }
        }
    }

    /**
     * @param list<array{
     *     path:string,target:string,candidate:?string,previous:?string,
     *     delete:bool,old_moved:bool,new_installed:bool
     * }> $operations
     */
    private function cleanupCandidates(array $operations): void
    {
        foreach ($operations as $operation) {
            $candidate = $operation['candidate'];
            if ($candidate !== null && is_file($candidate)) {
                @unlink($candidate);
            }
        }
    }

    private function targetPath(string $relative, bool $createDirectories): string
    {
        if (
            $relative === ''
            || str_starts_with($relative, '/')
            || str_starts_with($relative, '\\')
            || str_contains($relative, "\0")
            || str_contains($relative, '\\')
        ) {
            throw new RuntimeException('Пакет содержит небезопасный целевой путь.');
        }

        $segments = explode('/', $relative);
        $filename = array_pop($segments);

        if (
            !is_string($filename)
            || $filename === ''
            || $filename === '.'
            || $filename === '..'
        ) {
            throw new RuntimeException('Пакет содержит некорректное имя файла.');
        }

        $root = realpath($this->root);
        if ($root === false || !is_dir($root) || is_link($this->root)) {
            throw new RuntimeException('Корневой каталог ChurchCMS недоступен.');
        }

        $current = $root;

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Пакет содержит небезопасный путь каталога.');
            }

            $current .= DIRECTORY_SEPARATOR . $segment;

            if (is_link($current)) {
                throw new RuntimeException(
                    'Целевой каталог обновления не должен быть символической ссылкой.'
                );
            }

            if (is_dir($current)) {
                continue;
            }

            if (file_exists($current)) {
                throw new RuntimeException(
                    'Целевой каталог обновления имеет неподдерживаемый тип.'
                );
            }

            if (
                $createDirectories
                && !mkdir($current, 0750)
                && !is_dir($current)
            ) {
                throw new RuntimeException(
                    'Не удалось подготовить каталог для файла обновления.'
                );
            }
        }

        return $current . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * @param array{path:string,bytes:int,sha256:string} $entry
     */
    private function assertFileChecksum(string $path, array $entry): void
    {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Файл обновления отсутствует или небезопасен.');
        }

        $size = filesize($path);
        $hash = hash_file('sha256', $path);

        if (
            $size !== $entry['bytes']
            || !is_string($hash)
            || !hash_equals($entry['sha256'], $hash)
        ) {
            throw new RuntimeException(
                'Контрольная сумма применённого файла обновления не совпала.'
            );
        }
    }
}
