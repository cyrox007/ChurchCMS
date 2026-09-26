#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Modules\Social\ChannelSyncService;

$root = dirname(__DIR__);
require $root . '/core.php';

try {
    $result = (new ChannelSyncService())->syncAll();

    echo 'Connections: ' . $result['connections'] . PHP_EOL;
    echo 'Imported to inbox: ' . $result['items'] . PHP_EOL;
    echo 'Errors: ' . $result['errors'] . PHP_EOL;

    exit($result['errors'] > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Channel sync failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
