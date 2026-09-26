#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\MigrationRunner;

$root = dirname(__DIR__);
require $root . '/core.php';

try {
    $runner = new MigrationRunner(DatabaseManager::getInstance(), $root);
    $applied = $runner->migrate();

    if ($applied === []) {
        echo "No pending migrations.\n";
        exit(0);
    }

    foreach ($applied as $id) {
        echo "Applied: {$id}\n";
    }

    echo "Migration complete.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
    exit(1);
}
