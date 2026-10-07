#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Modules\DistributionWebhooks\DistributionWebhookWorker;

$root = dirname(__DIR__);
require $root . '/core.php';

$limit = 25;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = (int) substr($argument, strlen('--limit='));
        continue;
    }

    fwrite(
        STDERR,
        "Использование: php bin/distribution-webhooks.php [--limit=25]\n",
    );
    exit(2);
}

try {
    $summary = DistributionWebhookWorker::fromDatabase()->run(
        max(1, min(100, $limit)),
    );

    echo sprintf(
        "Исходящие webhooks: обработано %d, доставлено %d, повтор %d, остановлено %d.\n",
        $summary['processed'],
        $summary['delivered'],
        $summary['retried'],
        $summary['dead'],
    );
    exit(0);
} catch (Throwable $error) {
    fwrite(
        STDERR,
        "Worker исходящих webhooks завершился ошибкой: "
        . $error->getMessage()
        . "\n",
    );
    exit(1);
}
