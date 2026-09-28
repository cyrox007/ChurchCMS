<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;
use Throwable;

final class BackupRestoreOrchestrator
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly string $root,
        private readonly string $backupRoot,
    ) {
    }

    /**
     * Восстанавливает БД, локальную конфигурацию и uploads одной операцией.
     * Перед изменениями всегда создаётся аварийная копия текущего состояния.
     *
     * @return array{
     *     id:string,
     *     safety_backup_id:string,
     *     tables:int,
     *     rows:int,
     *     files:int
     * }
     */
    public function restore(string $backupId): array
    {
        $manager = $this->backupManager();
        $manager->verify($backupId);
        $this->assertBrowserSafeConfiguration($backupId);

        $safety = $manager->create();

        try {
            $database = $this->databaseRestore()->restore($backupId);
            $files = $this->fileRestore()->restore($backupId);
            $this->assertHealthy();

            return [
                'id' => $backupId,
                'safety_backup_id' => $safety['id'],
                'tables' => $database['tables'],
                'rows' => $database['rows'],
                'files' => $files['files'],
            ];
        } catch (Throwable $error) {
            $this->rollback($safety['id'], $error);
        }
    }

    private function rollback(
        string $safetyBackupId,
        Throwable $originalError,
    ): never {
        try {
            $this->databaseRestore()->restore($safetyBackupId);
            $this->fileRestore()->restore($safetyBackupId);
            $this->assertHealthy();
        } catch (Throwable $rollbackError) {
            error_log(
                'ChurchCMS восстановление аварийной копии не завершено: '
                . $rollbackError->getMessage()
            );

            throw new RuntimeException(
                'Восстановление прервано, а автоматический возврат аварийной '
                . 'копии завершился не полностью. Требуется ручная проверка.',
                0,
                $originalError,
            );
        }

        throw new RuntimeException(
            'Восстановление не завершено. Текущее состояние возвращено '
            . 'из аварийной резервной копии.',
            0,
            $originalError,
        );
    }

    private function assertHealthy(): void
    {
        $health = (new InstallationHealthCheck(
            $this->database,
            $this->root,
        ))->check();

        if (($health['ready'] ?? false) !== true) {
            throw new RuntimeException(
                'После восстановления installation healthcheck не пройден.'
            );
        }
    }

    /**
     * Browser restore не должен незаметно переключить установку на другую БД
     * или потерять каталог аварийной копии после следующего запроса.
     */
    private function assertBrowserSafeConfiguration(
        string $backupId,
    ): void {
        $target = $this->backupConfiguration($backupId);

        $driver = (string) self::arrayValue(
            $target,
            'database.driver',
            'pgsql',
        );
        $port = $driver === 'pgsql' ? 5432 : 3306;

        $databaseKeys = [
            'database.driver' => 'pgsql',
            'database.host' => '127.0.0.1',
            'database.port' => $port,
            'database.database' => '',
            'database.username' => '',
            'database.password' => '',
        ];

        foreach ($databaseKeys as $key => $default) {
            if (
                (string) self::arrayValue($target, $key, $default)
                !== (string) Config::get($key, $default)
            ) {
                throw new RuntimeException(
                    'Для восстановления из Admin Shell резервная копия '
                    . 'должна использовать ту же конфигурацию подключения к БД.'
                );
            }
        }

        $targetBackupRoot = (string) self::arrayValue(
            $target,
            'operations.backup_path',
            '',
        );
        $currentBackupRoot = (string) Config::get(
            'operations.backup_path',
            '',
        );

        if (
            $targetBackupRoot === ''
            || $currentBackupRoot === ''
            || $targetBackupRoot !== $currentBackupRoot
        ) {
            throw new RuntimeException(
                'Для восстановления из Admin Shell каталог резервных копий '
                . 'не должен меняться.'
            );
        }

        $secret = base64_decode(
            trim((string) self::arrayValue(
                $target,
                'security.secret_key',
                '',
            )),
            true,
        );

        if (
            self::arrayValue(
                $target,
                'installation.completed',
                false,
            ) !== true
            || !is_string($secret)
            || strlen($secret) !== 32
        ) {
            throw new RuntimeException(
                'Резервная копия содержит конфигурацию незавершённой '
                . 'или повреждённой установки.'
            );
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function backupConfiguration(string $backupId): array
    {
        $root = realpath($this->backupRoot);
        if ($root === false || !is_dir($root) || is_link($root)) {
            throw new RuntimeException(
                'Каталог резервных копий недоступен.'
            );
        }

        $path = $root
            . DIRECTORY_SEPARATOR
            . $backupId
            . DIRECTORY_SEPARATOR
            . 'config'
            . DIRECTORY_SEPARATOR
            . 'local.php';

        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException(
                'В резервной копии отсутствует безопасная локальная конфигурация.'
            );
        }

        try {
            $values = (static function (string $file): mixed {
                return require $file;
            })($path);
        } catch (Throwable $error) {
            throw new RuntimeException(
                'Не удалось проверить локальную конфигурацию резервной копии.',
                0,
                $error,
            );
        }

        if (!is_array($values)) {
            throw new RuntimeException(
                'Локальная конфигурация резервной копии имеет неверный формат.'
            );
        }

        return $values;
    }

    private static function arrayValue(
        array $values,
        string $key,
        mixed $default,
    ): mixed {
        $cursor = $values;

        foreach (explode('.', $key) as $segment) {
            if (
                !is_array($cursor)
                || !array_key_exists($segment, $cursor)
            ) {
                return $default;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    private function backupManager(): BackupManager
    {
        return new BackupManager(
            $this->database,
            $this->root,
            $this->backupRoot,
        );
    }

    private function databaseRestore(): DatabaseRestoreManager
    {
        return new DatabaseRestoreManager(
            $this->database,
            $this->root,
            $this->backupRoot,
        );
    }

    private function fileRestore(): FileRestoreManager
    {
        return new FileRestoreManager(
            $this->database,
            $this->root,
            $this->backupRoot,
        );
    }
}
