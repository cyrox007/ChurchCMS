#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Modules\Media\MediaStorageMaintenanceService;
use ChurchCMS\Modules\Media\MediaStorageReportService;

require dirname(__DIR__) . '/core.php';

$options = getopt('', [
    'action:',
    'older-than::',
    'limit::',
    'apply',
    'delete-local',
    'record-snapshot',
]);

$action = strtolower(trim((string) ($options['action'] ?? 'report')));
$olderThan = max(1, (int) ($options['older-than'] ?? 90));
$limit = max(1, (int) ($options['limit'] ?? 100));
$apply = array_key_exists('apply', $options);
$deleteLocal = array_key_exists('delete-local', $options);

try {
    if ($action === 'report') {
        $report = MediaStorageReportService::fromConfig()->report(
            array_key_exists('record-snapshot', $options),
        );
        echo json_encode(
            $report,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL;
        exit(0);
    }

    $maintenance = MediaStorageMaintenanceService::fromConfig();

    if ($action === 'archive') {
        $summary = $maintenance->archiveOld(
            olderThanDays: $olderThan,
            limit: $limit,
            removeLocal: $deleteLocal,
            apply: $apply,
        );
        echo ($apply ? 'Архивирование Media' : 'Dry-run архивирования Media') . PHP_EOL;
        echo json_encode(
            $summary,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL;
        exit($summary['errors'] === [] ? 0 : 2);
    }

    if ($action === 'prune-cache') {
        $summary = $maintenance->pruneArchiveCache(
            olderThanDays: $olderThan,
            limit: $limit,
            apply: $apply,
        );
        echo ($apply ? 'Очистка локального Media cache' : 'Dry-run очистки Media cache') . PHP_EOL;
        echo json_encode(
            $summary,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL;
        exit($summary['errors'] === [] ? 0 : 2);
    }

    fwrite(
        STDERR,
        "Неизвестное действие. Используйте report, archive или prune-cache.\n",
    );
    exit(64);
} catch (Throwable $error) {
    fwrite(
        STDERR,
        'Media storage: ' . str_replace(["\r", "\n", "\0"], [' ', ' ', ''], $error->getMessage()) . PHP_EOL,
    );
    exit(1);
}
