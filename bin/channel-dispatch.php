#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\Config;
use ChurchCMS\Modules\Social\ChannelOutboundDispatcher;

$root = dirname(__DIR__);
require $root . '/core.php';

$limit = (int) Config::get(
    'social.dispatch_batch_size',
    20,
);
$maxAttempts = (int) Config::get(
    'social.max_attempts',
    5,
);

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = (int) substr(
            $argument,
            strlen('--limit='),
        );
        continue;
    }

    if (str_starts_with($argument, '--max-attempts=')) {
        $maxAttempts = (int) substr(
            $argument,
            strlen('--max-attempts='),
        );
        continue;
    }

    fwrite(
        STDERR,
        "Использование: php bin/channel-dispatch.php "
        . "[--limit=20] [--max-attempts=5]\n",
    );
    exit(2);
}

try {
    $summary = ChannelOutboundDispatcher::fromDatabase()
        ->dispatch(
            $limit,
            $maxAttempts,
        );

    echo sprintf(
        "Внешняя очередь: получено %d; отправлено %d; "
        . "повтор %d; dead-letter %d; пропущено %d\n",
        $summary['claimed'],
        $summary['sent'],
        $summary['retried'],
        $summary['dead_letter'],
        $summary['skipped'],
    );
    exit(0);
} catch (Throwable $error) {
    fwrite(
        STDERR,
        "Worker внешних каналов завершился ошибкой: "
        . $error->getMessage()
        . "\n",
    );
    exit(1);
}
