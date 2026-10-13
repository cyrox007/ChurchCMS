<?php

declare(strict_types=1);

use ChurchCMS\Core\LogManager;
use ChurchCMS\Core\RuntimeAutoloader;

$root = dirname(__DIR__);
require_once $root . '/core/RuntimeAutoloader.php';
RuntimeAutoloader::register($root);

function failLogSmoke(string $message): never
{
    fwrite(STDERR, "Ошибка smoke журналов: {$message}\n");
    exit(1);
}

function removeLogSmokeDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $entries = scandir($directory);
    if (!is_array($entries)) {
        return;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        if (is_link($path) || is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($directory);
}

$directory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'churchcms-log-smoke-'
    . bin2hex(random_bytes(6));
if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
    failLogSmoke('не удалось создать временный каталог');
}

try {
    $manager = new LogManager(
        directory: $directory,
        maxBytes: 65536,
        maxArchives: 2,
        maxAgeDays: 2,
    );

    $first = str_repeat('A', 64000) . "\n";
    if (!$manager->append('error.log', $first)) {
        failLogSmoke('не удалось записать исходный журнал');
    }
    if (!$manager->append('error.log', str_repeat('B', 4096) . "\n")) {
        failLogSmoke('не удалось записать запись после порога');
    }

    $archives = glob($directory . '/error.log.*');
    if (!is_array($archives) || count($archives) !== 1) {
        failLogSmoke('автоматическая ротация по размеру не сработала');
    }
    $current = file_get_contents($directory . '/error.log');
    if (!is_string($current) || !str_starts_with($current, 'B')) {
        failLogSmoke('новая запись не попала в новый журнал');
    }

    $result = $manager->maintain('error.log', true);
    if (!$result['rotated'] || $result['archives'] !== 2) {
        failLogSmoke('принудительная ротация не создала второй архив');
    }

    file_put_contents($directory . '/error.log', "новый журнал\n");
    $manager->maintain('error.log', true);
    $afterLimit = glob($directory . '/error.log.*');
    if (!is_array($afterLimit) || count($afterLimit) !== 2) {
        failLogSmoke('retention по количеству архивов не соблюдён');
    }

    $oldArchive = $directory . '/error.log.20000101-000000-1-abcdef';
    file_put_contents($oldArchive, "старый архив\n");
    touch($oldArchive, time() - (5 * 86400));
    $pruned = $manager->maintain('error.log');
    if ($pruned['removed'] < 1 || file_exists($oldArchive)) {
        failLogSmoke('устаревший архив не удалён');
    }

    if (function_exists('symlink')) {
        $target = $directory . '/target.log';
        file_put_contents($target, "target\n");
        @unlink($directory . '/error.log');
        if (@symlink($target, $directory . '/error.log')) {
            try {
                $manager->append('error.log', "опасная запись\n");
                failLogSmoke('симлинк журнала не был отклонён');
            } catch (RuntimeException) {
            }
        }
    }

    echo "Smoke ротации журналов пройден.\n";
} finally {
    removeLogSmokeDirectory($directory);
}
