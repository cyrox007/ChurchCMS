<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use PDO;

/**
 * Миграция, которая явно описывает безопасный обратный переход схемы.
 */
interface ReversibleMigration extends Migration
{
    public function down(PDO $pdo, string $driver): void;
}
