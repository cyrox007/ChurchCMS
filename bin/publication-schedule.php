#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Modules\Publications\PublicationScheduleWorker;

$root = dirname(__DIR__);
require $root . '/core.php';

$limit = 50;
$siteKey = 'default';

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = (int) substr(
            $argument,
            strlen('--limit='),
        );
        continue;
    }

    if (str_starts_with($argument, '--site=')) {
        $siteKey = trim(substr(
            $argument,
            strlen('--site='),
        ));
        continue;
    }

    fwrite(
        STDERR,
        "Использование: php bin/publication-schedule.php "
        . "[--limit=50] [--site=default]\n",
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
    $summary = PublicationScheduleWorker::fromDatabase()
        ->run(
            limit: max(1, min(200, $limit)),
            siteKey: $siteKey,
        );

    echo sprintf(
        "Отложенные публикации: опубликовано %d.\n",
        $summary['published'],
    );
    exit(0);
} catch (Throwable $error) {
    fwrite(
        STDERR,
        "Worker отложенных публикаций завершился ошибкой: "
        . $error->getMessage()
        . "\n",
    );
    exit(1);
}
