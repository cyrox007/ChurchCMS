<?php

declare(strict_types=1);

use ChurchCMS\Modules\Social\YoutubeConnectionConfiguration;

require dirname(__DIR__) . '/core.php';

$inbound = YoutubeConnectionConfiguration::fromInput(
    [
        'api_key' => 'api-key',
        'privacy' => 'unlisted',
        'category_id' => '22',
    ],
    false,
);
$decoded = json_decode(
    $inbound['credentials'],
    true,
    32,
    JSON_THROW_ON_ERROR,
);

if (
    ($decoded['api_key'] ?? null) !== 'api-key'
    || ($decoded['access_token'] ?? null) !== ''
    || ($inbound['settings']['youtube_privacy'] ?? null) !== 'unlisted'
    || ($inbound['settings']['youtube_category_id'] ?? null) !== '22'
) {
    fwrite(STDERR, "YouTube inbound-конфигурация собрана неверно.\n");
    exit(1);
}

$outbound = YoutubeConnectionConfiguration::fromInput(
    [
        'api_key' => 'api-key',
        'refresh_token' => 'refresh-token',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'privacy' => 'private',
        'category_id' => '29',
    ],
    true,
);
$outboundDecoded = json_decode(
    $outbound['credentials'],
    true,
    32,
    JSON_THROW_ON_ERROR,
);

if (
    ($outboundDecoded['refresh_token'] ?? null) !== 'refresh-token'
    || ($outboundDecoded['client_id'] ?? null) !== 'client-id'
    || ($outboundDecoded['client_secret'] ?? null) !== 'client-secret'
    || ($outbound['settings']['youtube_privacy'] ?? null) !== 'private'
    || ($outbound['settings']['youtube_category_id'] ?? null) !== '29'
) {
    fwrite(STDERR, "YouTube outbound-конфигурация собрана неверно.\n");
    exit(1);
}

$failed = false;
try {
    YoutubeConnectionConfiguration::fromInput(
        [
            'api_key' => 'api-key',
            'privacy' => 'unlisted',
            'category_id' => '22',
        ],
        true,
    );
} catch (\InvalidArgumentException) {
    $failed = true;
}

if (!$failed) {
    fwrite(STDERR, "Outbound YouTube принят без OAuth.\n");
    exit(1);
}

$failed = false;
try {
    YoutubeConnectionConfiguration::fromInput(
        [
            'api_key' => 'api-key',
            'refresh_token' => 'refresh-token',
            'client_id' => 'client-id',
            'privacy' => 'public',
            'category_id' => '22',
        ],
        true,
    );
} catch (\InvalidArgumentException) {
    $failed = true;
}

if (!$failed) {
    fwrite(STDERR, "Неполный OAuth refresh-набор ошибочно принят.\n");
    exit(1);
}

$failed = false;
try {
    YoutubeConnectionConfiguration::settings([
        'privacy' => 'unknown',
        'category_id' => '22',
    ]);
} catch (\InvalidArgumentException) {
    $failed = true;
}

if (!$failed) {
    fwrite(STDERR, "Некорректная приватность YouTube ошибочно принята.\n");
    exit(1);
}

echo "YouTube Admin settings smoke OK\n";
