#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\FederationPublicationSyncWorker;

$root = dirname(__DIR__);
require $root . '/core.php';

$siteKey = 'default';
$linkLimit = 20;
$pageSize = 100;

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--site=')) {
        $siteKey = trim(substr(
            $argument,
            strlen('--site='),
        ));
        continue;
    }

    if (str_starts_with($argument, '--links=')) {
        $linkLimit = (int) substr(
            $argument,
            strlen('--links='),
        );
        continue;
    }

    if (str_starts_with($argument, '--page-size=')) {
        $pageSize = (int) substr(
            $argument,
            strlen('--page-size='),
        );
        continue;
    }

    fwrite(
        STDERR,
        "Использование: php bin/federation-sync.php "
        . "[--site=default] [--links=20] [--page-size=100]\n",
    );
    exit(2);
}

if (
    preg_match(
        '/^[a-z0-9][a-z0-9_.-]{0,63}$/D',
        $siteKey,
    ) !== 1
) {
    fwrite(
        STDERR,
        "Некорректный идентификатор сайта.\n",
    );
    exit(2);
}

try {
    $summary = FederationPublicationSyncWorker::fromDatabase()
        ->run(
            siteKey: $siteKey,
            linkLimit: max(1, min(100, $linkLimit)),
            pageSize: max(1, min(100, $pageSize)),
        );

    echo sprintf(
        "Federation sync: связей %d, успешно %d, ошибок %d, "
        . "проекций %d, tombstone %d, требуют продолжения %d.\n",
        $summary['links'],
        $summary['succeeded'],
        $summary['failed'],
        $summary['projections'],
        $summary['tombstones'],
        $summary['pending'],
    );

    exit($summary['failed'] > 0 ? 1 : 0);
} catch (Throwable $error) {
    fwrite(
        STDERR,
        "Синхронизация публикаций завершилась ошибкой: "
        . $error->getMessage()
        . "\n",
    );
    exit(1);
}
