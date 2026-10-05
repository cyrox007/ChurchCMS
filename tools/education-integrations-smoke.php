<?php

declare(strict_types=1);

use ChurchCMS\Modules\EducationIntegrations\EducationPlatformAdapterRegistry;
use ChurchCMS\Modules\EducationIntegrations\EducationPlatformEndpoint;
use ChurchCMS\Modules\EducationIntegrations\EducationPlatformHttpClient;
use ChurchCMS\Modules\EducationIntegrations\MoodleEducationAdapter;
use ChurchCMS\Modules\EducationIntegrations\OjsEducationAdapter;
use InvalidArgumentException;

require dirname(__DIR__) . '/core.php';

$http = new class implements EducationPlatformHttpClient {
    /** @var list<array<string,mixed>> */
    public array $requests = [];

    public function getJson(string $url, array $headers = []): array
    {
        $this->requests[] = ['method' => 'GET', 'url' => $url, 'headers' => $headers];
        if (str_contains($url, '/_/api/v1/contexts')) {
            return ['status' => 200, 'body' => '{"items":[{"id":1}]}', 'json' => ['items' => [['id' => 1]]]];
        }
        return ['status' => 200, 'body' => '{"items":[{"id":7}]}', 'json' => ['items' => [['id' => 7]]]];
    }

    public function postForm(string $url, array $payload, array $headers = []): array
    {
        $this->requests[] = ['method' => 'POST', 'url' => $url, 'payload' => $payload, 'headers' => $headers];
        if (($payload['wsfunction'] ?? '') === 'core_webservice_get_site_info') {
            return [
                'status' => 200,
                'body' => '{"sitename":"Учебный портал","siteurl":"https://moodle.example.org","release":"5.3"}',
                'json' => ['sitename' => 'Учебный портал', 'siteurl' => 'https://moodle.example.org', 'release' => '5.3'],
            ];
        }
        return ['status' => 200, 'body' => '[{"id":2,"fullname":"Курс"}]', 'json' => [['id' => 2, 'fullname' => 'Курс']]];
    }
};

$moodle = new MoodleEducationAdapter('https://moodle.example.org', 'moodle-secret', $http);
$moodleResult = $moodle->testConnection();
$courses = $moodle->courses();
if (
    $moodleResult['ok'] !== true
    || ($moodleResult['metadata']['site_name'] ?? '') !== 'Учебный портал'
    || count($courses) !== 1
) {
    fwrite(STDERR, "Moodle adapter вернул некорректный результат.\n");
    exit(1);
}

$moodleRequest = $http->requests[0] ?? [];
if (
    ($moodleRequest['url'] ?? '') !== 'https://moodle.example.org/webservice/rest/server.php'
    || str_contains((string) ($moodleRequest['url'] ?? ''), 'moodle-secret')
    || (($moodleRequest['payload']['wstoken'] ?? '') !== 'moodle-secret')
    || (($moodleRequest['payload']['wsfunction'] ?? '') !== 'core_webservice_get_site_info')
) {
    fwrite(STDERR, "Moodle adapter неправильно формирует безопасный Web Service запрос.\n");
    exit(1);
}

$ojs = new OjsEducationAdapter('https://journals.example.org', 'ojs-secret', 'theology', $http);
$ojsResult = $ojs->testConnection();
$submissions = $ojs->submissions();
if ($ojsResult['ok'] !== true || count($submissions) !== 1) {
    fwrite(STDERR, "OJS adapter вернул некорректный результат.\n");
    exit(1);
}

$ojsContextRequest = $http->requests[2] ?? [];
$ojsSubmissionRequest = $http->requests[3] ?? [];
if (
    ($ojsContextRequest['url'] ?? '') !== 'https://journals.example.org/_/api/v1/contexts'
    || ($ojsContextRequest['headers']['Authorization'] ?? '') !== 'Bearer ojs-secret'
    || str_contains((string) ($ojsContextRequest['url'] ?? ''), 'ojs-secret')
    || ($ojsSubmissionRequest['url'] ?? '') !== 'https://journals.example.org/theology/api/v1/submissions'
) {
    fwrite(STDERR, "OJS adapter неправильно формирует API запрос.\n");
    exit(1);
}

$registry = new EducationPlatformAdapterRegistry();
$registry->register($moodle);
$registry->register($ojs);
if (count($registry->all()) !== 2 || $registry->get('moodle') !== $moodle || $registry->get('ojs') !== $ojs) {
    fwrite(STDERR, "Реестр образовательных адаптеров работает некорректно.\n");
    exit(1);
}

foreach ([
    'http://moodle.example.org',
    'https://user:password@example.org',
    'https://example.org?token=secret',
    'https://localhost',
    'https://127.0.0.1',
    'https://10.0.0.1',
] as $invalidUrl) {
    try {
        EducationPlatformEndpoint::baseUrl($invalidUrl);
        fwrite(STDERR, "Разрешён небезопасный адрес интеграции: {$invalidUrl}.\n");
        exit(1);
    } catch (InvalidArgumentException) {
    }
}

try {
    new OjsEducationAdapter('https://journals.example.org', 'token', '../admin', $http);
    fwrite(STDERR, "OJS adapter разрешил небезопасный journal path.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Проверка Moodle/OJS адаптеров пройдена.\n";
