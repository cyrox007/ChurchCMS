<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'ChurchCMS',
        'version' => '0.1.0-dev',
        'php_min' => '8.3',
        'timezone' => 'Europe/Moscow',
        'locale' => 'ru',
    ],
    'database' => [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'churchcms',
        'username' => 'churchcms',
        'password' => '',
    ],
    'theme' => [
        'active' => 'default',
    ],
];
