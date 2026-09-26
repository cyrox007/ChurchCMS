#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\BackupManager;
use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\DatabaseRestoreManager;
use ChurchCMS\Core\FilesystemRestoreManager;

$root = dirname(__DIR__);
require $root . '/core.php';

$command = $argv[1] ?? 'create';
$positionals = [];
$backupPath = null;
$confirmation = null;

foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--path=')) {
        $backupPath = substr($argument, strlen('--path='));
        continue;
    }

    if (str_starts_with($argument, '--confirm=')) {
        $confirmation = substr($argument, strlen('--confirm='));
        continue;
    }

    $positionals[] = $argument;
}

$backupPath ??= (string) Config::get('operations.backup_path', '');

try {
    if ($backupPath === '') {
        throw new RuntimeException('Не настроен каталог резервных копий.');
    }

    $manager = new BackupManager(
        DatabaseManager::getInstance(),
        $root,
        $backupPath,
    );

    if ($command === 'create') {
        $result = $manager->create();
        echo sprintf(
            "Резервная копия создана и проверена: %s; файлов: %d; таблиц: %d\n",
            $result['id'],
            $result['files'],
            $result['tables'],
        );
        exit(0);
    }

    if ($command === 'verify') {
        $backupId = (string) ($positionals[0] ?? '');
        if ($backupId === '') {
            fwrite(STDERR, "Укажите идентификатор резервной копии.\n");
            exit(2);
        }

        $manager->verify($backupId);
        echo "Резервная копия проверена: {$backupId}\n";
        exit(0);
    }

    if ($command === 'restore-db') {
        $backupId = (string) ($positionals[0] ?? '');

        if ($backupId === '' || $confirmation !== $backupId) {
            fwrite(
                STDERR,
                "Для восстановления БД укажите идентификатор копии и повторите его в --confirm=<идентификатор>.\n",
            );
            exit(2);
        }

        $restore = new DatabaseRestoreManager(
            DatabaseManager::getInstance(),
            $root,
            $backupPath,
        );
        $result = $restore->restore($backupId);

        echo sprintf(
            "База данных восстановлена: %s; таблиц: %d; строк: %d\n",
            $backupId,
            $result['tables'],
            $result['rows'],
        );
        exit(0);
    }

    if ($command === 'restore-files') {
        $backupId = (string) ($positionals[0] ?? '');

        if ($backupId === '' || $confirmation !== $backupId) {
            fwrite(
                STDERR,
                "Для восстановления файлов укажите идентификатор копии и повторите его в --confirm=<идентификатор>.\n",
            );
            exit(2);
        }

        $restore = new FilesystemRestoreManager(
            DatabaseManager::getInstance(),
            $root,
            $backupPath,
        );
        $result = $restore->restore($backupId);

        echo sprintf(
            "Конфигурация и пользовательские файлы восстановлены: %s; файлов: %d\n",
            $backupId,
            $result['files'],
        );
        exit(0);
    }

    fwrite(
        STDERR,
        "Использование: php bin/backup.php create [--path=/абсолютный/путь], "
        . "php bin/backup.php verify <идентификатор> [--path=/абсолютный/путь], "
        . "php bin/backup.php restore-db <идентификатор> "
        . "--confirm=<идентификатор> [--path=/абсолютный/путь] "
        . "или php bin/backup.php restore-files <идентификатор> "
        . "--confirm=<идентификатор> [--path=/абсолютный/путь]\n",
    );
    exit(2);
} catch (Throwable $e) {
    error_log('ChurchCMS backup: ' . $e->getMessage());
    fwrite(
        STDERR,
        "Операция резервного копирования не выполнена. Проверьте права на каталог и состояние базы данных.\n",
    );
    exit(1);
}
