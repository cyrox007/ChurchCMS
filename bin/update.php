#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\Config;
use ChurchCMS\Core\UpdatePackageStager;

$root = dirname(__DIR__);
require $root . '/core.php';

$command = $argv[1] ?? '';
$positionals = [];
$stagingPath = null;

foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--path=')) {
        $stagingPath = substr($argument, strlen('--path='));
        continue;
    }

    $positionals[] = $argument;
}

$stagingPath ??= (string) Config::get('operations.update_staging_path', '');

try {
    if ($command !== 'stage') {
        fwrite(
            STDERR,
            "Использование: php bin/update.php stage /абсолютный/путь/к/пакету "
            . "[--path=/абсолютный/staging]\n",
        );
        exit(2);
    }

    $source = (string) ($positionals[0] ?? '');
    if ($source === '' || $stagingPath === '') {
        fwrite(STDERR, "Укажите пакет обновления и staging-каталог.\n");
        exit(2);
    }

    $result = (new UpdatePackageStager($root, $stagingPath))->stage($source);

    echo sprintf(
        "Пакет проверен и помещён в staging: %s; версия: %s; файлов: %d; удалений: %d\n",
        $result['id'],
        $result['version'],
        $result['files'],
        $result['deleted_files'],
    );
    exit(0);
} catch (Throwable $e) {
    error_log('ChurchCMS update staging: ' . $e->getMessage());
    fwrite(
        STDERR,
        "Пакет обновления не прошёл staging-проверку. "
        . "Проверьте версию, PHP, манифест и контрольные суммы.\n",
    );
    exit(1);
}
