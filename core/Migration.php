<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use PDO;

interface Migration
{
    public function id(): string;

    public function up(PDO $pdo, string $driver): void;
}
