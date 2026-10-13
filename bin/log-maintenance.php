#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\LogManager;
use ChurchCMS\Core\RuntimeAutoloader;

$root = dirname(__DIR__);
require_once $root . '/core/RuntimeAutoloader.php';
RuntimeAutoloader::register($root);

$forceRotate = false;
$maxBytes = LogManager::DEFAULT_MAX_BYTES;
$maxArchives = LogManager::DEFAULT_MAX_ARCHIVES;
$maxAgeDays = LogManager::DEFAULT_MAX_AGE_DAYS;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--rotate') {
        $forceRotate = true;
        continue;
    }
    if (str_starts_with($argument, '--max-bytes=')) {
        $maxBytes = (int) substr($argument, strlen('--max-bytes='));
        continue;
    }
    if (str_starts_with($argument, '--max-archives=')) {
        $maxArchives = (int) substr($argument, strlen('--max-archives='));
        continue;
    }
    if (str_starts_with($argument, '--max-age-days=')) {
        $maxAgeDays = (int) substr($argument, strlen('--max-age-days='));
        continue;
    }

    fwrite(
        STDERR,
        "Использование: php bin/log-maintenance.php [--rotate] "
        . "[--max-bytes=10485760] [--max-archives=10] [--max-age-days=30]\n",
    );
    exit(2);
}

try {
    $manager = new LogManager(
        directory: $root . '/storage/logs',
        maxBytes: $maxBytes,
        maxArchives: $maxArchives,
        maxAgeDays: $maxAgeDays,
    );
    $result = $manager->maintain('error.log', $forceRotate);

    echo sprintf(
        "Обслуживание журналов завершено: ротация=%s, удалено=%d, текущий=%d байт, архивов=%d.\n",
        $result['rotated'] ? 'да' : 'нет',
        $result['removed'],
        $result['current_bytes'],
        $result['archives'],
    );
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Ошибка обслуживания журналов: ' . $error->getMessage() . "\n");
    exit(1);
}
