#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\UpdatePackageApplier;
use ChurchCMS\Core\UpdatePackageStager;

$root = dirname(__DIR__);
require $root . '/core.php';

$command = $argv[1] ?? '';
$positionals = [];
$stagingPath = null;
$backupPath = null;
$confirmation = null;

foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--path=')) {
        $stagingPath = substr($argument, strlen('--path='));
        continue;
    }

    if (str_starts_with($argument, '--backup-path=')) {
        $backupPath = substr($argument, strlen('--backup-path='));
        continue;
    }

    if (str_starts_with($argument, '--confirm=')) {
        $confirmation = substr($argument, strlen('--confirm='));
        continue;
    }

    $positionals[] = $argument;
}

$stagingPath ??= (string) Config::get('operations.update_staging_path', '');
$backupPath ??= (string) Config::get('operations.backup_path', '');

try {
    if ($command === 'stage') {
        $source = (string) ($positionals[0] ?? '');
        if ($source === '' || $stagingPath === '') {
            fwrite(STDERR, "Укажите пакет обновления и staging-каталог.\n");
            exit(2);
        }

        $result = (new UpdatePackageStager($root, $stagingPath))->stage($source);

        echo sprintf(
            "Пакет проверен и помещён в staging: %s; версия: %s; файлов: %d; удалений: %d; подпись: %s\n",
            $result['id'],
            $result['version'],
            $result['files'],
            $result['deleted_files'],
            $result['signature_key_id'],
        );
        exit(0);
    }

    if ($command === 'apply') {
        $stageId = (string) ($positionals[0] ?? '');

        if (
            $stageId === ''
            || $stagingPath === ''
            || $backupPath === ''
            || $confirmation !== $stageId
        ) {
            fwrite(
                STDERR,
                "Для применения обновления укажите staging ID и повторите его "
                . "в --confirm=<staging-id>. Также должны быть доступны staging и backup каталоги.\n",
            );
            exit(2);
        }

        $result = (new UpdatePackageApplier(
            DatabaseManager::getInstance(),
            $root,
            $stagingPath,
            $backupPath,
        ))->apply($stageId);

        echo sprintf(
            "Обновление применено: %s; версия: %s; backup: %s; файлов: %d; удалений: %d; подпись: %s\n",
            $result['id'],
            $result['version'],
            $result['backup_id'],
            $result['files'],
            $result['deleted_files'],
            $result['signature_key_id'],
        );
        exit(0);
    }

    fwrite(
        STDERR,
        "Использование:\n"
        . "  php bin/update.php stage /абсолютный/путь/к/пакету "
        . "[--path=/абсолютный/staging]\n"
        . "  php bin/update.php apply <staging-id> --confirm=<staging-id> "
        . "[--path=/абсолютный/staging] [--backup-path=/абсолютный/backups]\n",
    );
    exit(2);
} catch (Throwable $e) {
    error_log('ChurchCMS обновление: ' . $e->getMessage());
    fwrite(
        STDERR,
        "Операция обновления не выполнена: " . $e->getMessage() . "\n",
    );
    exit(1);
}
