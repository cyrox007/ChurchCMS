<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'ChurchCMS',
        'version' => '0.1.0-dev',
        'php_min' => '8.3',
        'timezone' => 'Europe/Moscow',
        'locale' => 'ru',
        'url' => 'http://localhost',
    ],
    'session' => [
        'name' => 'churchcms_session',
        'lifetime_seconds' => 28800,
        'same_site' => 'Lax',
    ],
    'security' => [
        'csp_report_only' => false,
        // Generated during installation and overridden in config/local.php.
        'secret_key' => '',
    ],
    'social' => [
        'enabled' => true,
        'dispatch_batch_size' => 20,
        'max_attempts' => 5,
    ],
    'performance' => [
        'page_cache' => [
            'enabled' => true,
            'ttl_seconds' => 60,
            'stale_while_revalidate_seconds' => 300,
        ],
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
    'comments' => [
        'enabled' => true,
        'moderation' => 'premoderated',
        'max_length' => 4000,
        'rate_limit' => [
            'attempts' => 5,
            'window_seconds' => 300,
        ],
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
