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
    'syndication' => [
        'enabled' => true,
        'site_url' => 'http://localhost',
        'channel_title' => 'ChurchCMS',
        'channel_description' => 'ChurchCMS publication feed',
        'targets' => [
            'rss' => ['enabled' => true],
            'rambler' => ['enabled' => false],
        ],
    ],
    'api' => [
        'enabled' => true,
        'version' => 'v1',
        'public_cache_seconds' => 60,
        'allowed_origins' => [],
        'rate_limit' => [
            'public_per_minute' => 120,
            'partner_per_minute' => 600,
        ],
        // Secrets belong in config/local.php. Store only SHA-256 token hashes.
        'partner_tokens' => [],
    ],
];
