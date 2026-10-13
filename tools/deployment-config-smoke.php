#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $relative) use ($root, &$failures): string {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $failures[] = "Отсутствует файл {$relative}.";
        return '';
    }

    $content = file_get_contents($path);
    if ($content === false) {
        $failures[] = "Не удалось прочитать {$relative}.";
        return '';
    }

    return $content;
};

$expect = static function (
    string $content,
    string $needle,
    string $message,
) use (&$failures): void {
    if (!str_contains($content, $needle)) {
        $failures[] = $message;
    }
};

$nginx = $read('deploy/nginx/churchcms.conf.example');
$fpm = $read('deploy/php-fpm/churchcms.conf.example');
$cron = $read('deploy/cron/churchcms.example');
$docs = $read('docs/DEPLOYMENT.md');

$expect($nginx, 'listen 443 ssl;', 'Nginx-шаблон не включает HTTPS listener.');
$expect($nginx, 'client_max_body_size 512m;', 'Лимит Nginx не согласован с Media 512 МБ.');
$expect($nginx, 'location ~ ^/(?:app|bin|config|core|database|deploy|docs|legacy|modules|storage|themes|tools)', 'Nginx не закрывает внутренние каталоги.');
$expect($nginx, 'location ~ \\.php$', 'Nginx не запрещает прямой запуск произвольных PHP-файлов.');
$expect($nginx, 'fastcgi_param HTTPS on;', 'Nginx не передаёт HTTPS-состояние в PHP-FPM.');

$expect($fpm, 'user = churchcms', 'PHP-FPM должен выполняться от отдельного пользователя churchcms.');
$expect($fpm, 'upload_max_filesize] = 512M', 'PHP upload_max_filesize не согласован с Media 512 МБ.');
$expect($fpm, 'post_max_size] = 520M', 'PHP post_max_size должен быть больше upload_max_filesize.');
$expect($fpm, 'display_errors] = off', 'PHP-FPM не отключает вывод внутренних ошибок.');

$socket = '/run/php/php8.3-fpm-churchcms.sock';
$expect($nginx, $socket, 'Nginx использует другой PHP-FPM socket.');
$expect($fpm, 'listen = ' . $socket, 'PHP-FPM использует другой socket.');

$activeCron = array_values(array_filter(
    preg_split('/\R/', $cron) ?: [],
    static fn (string $line): bool => trim($line) !== '' && !str_starts_with(ltrim($line), '#'),
));

foreach ($activeCron as $line) {
    if (!str_contains($line, ' php ')) {
        continue;
    }
    if (!str_contains($line, ' churchcms ') || !str_contains($line, 'flock -n ')) {
        $failures[] = 'Каждая активная PHP cron-задача должна выполняться churchcms и использовать flock.';
        break;
    }
}

preg_match_all('#/var/www/churchcms/(bin/[a-z0-9-]+\.php)#', $cron, $matches);
foreach (array_unique($matches[1] ?? []) as $relative) {
    if (!is_file($root . '/' . $relative)) {
        $failures[] = "Cron ссылается на отсутствующую команду {$relative}.";
    }
}

foreach ([
    'nginx -t',
    'php-fpm8.3 -t',
    'bin/release-audit.php',
    'bin/migrate.php validate',
    'bin/backup.php create',
    '/health',
] as $required) {
    $expect($docs, $required, "В deployment-документации отсутствует обязательная проверка: {$required}.");
}

$installPosition = strpos($nginx, 'location = /install.php');
$phpBlockPosition = strpos($nginx, 'location ~ \\.php$');
if (
    $installPosition === false
    || $phpBlockPosition === false
    || $installPosition >= $phpBlockPosition
) {
    $failures[] = 'Отдельный install.php должен быть объявлен раньше общего запрета *.php.';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "ОШИБКА: {$failure}\n");
    }
    exit(1);
}

echo "Production-конфигурация развёртывания согласована.\n";
