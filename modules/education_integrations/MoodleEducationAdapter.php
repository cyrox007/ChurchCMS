<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationIntegrations;

use RuntimeException;

final class MoodleEducationAdapter implements EducationPlatformAdapter
{
    private string $baseUrl;
    private string $token;

    public function __construct(
        string $baseUrl,
        string $token,
        private readonly EducationPlatformHttpClient $http,
    ) {
        $this->baseUrl = EducationPlatformEndpoint::baseUrl($baseUrl);
        $this->token = trim($token);
        if ($this->token === '' || strlen($this->token) > 512) {
            throw new \InvalidArgumentException('Укажите токен Moodle Web Service.');
        }
    }

    public function id(): string
    {
        return 'moodle';
    }

    public function label(): string
    {
        return 'Moodle';
    }

    public function testConnection(): array
    {
        $json = $this->call('core_webservice_get_site_info');
        $siteName = trim((string) ($json['sitename'] ?? ''));
        if ($siteName === '') {
            throw new RuntimeException('Moodle не вернул сведения о сайте.');
        }
        return [
            'ok' => true,
            'message' => 'Соединение с Moodle проверено.',
            'metadata' => [
                'site_name' => $siteName,
                'site_url' => (string) ($json['siteurl'] ?? ''),
                'release' => (string) ($json['release'] ?? ''),
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function courses(): array
    {
        $json = $this->call('core_course_get_courses');
        if (!array_is_list($json)) {
            throw new RuntimeException('Moodle вернул неожиданный формат списка курсов.');
        }
        return array_values(array_filter($json, 'is_array'));
    }

    /** @return array<string,mixed> */
    private function call(string $function): array
    {
        $response = $this->http->postForm(
            $this->baseUrl . '/webservice/rest/server.php',
            [
                'wstoken' => $this->token,
                'wsfunction' => $function,
                'moodlewsrestformat' => 'json',
            ],
        );
        if ($response['status'] < 200 || $response['status'] >= 300 || $response['json'] === null) {
            throw new RuntimeException('Moodle API недоступен или вернул некорректный ответ.');
        }
        $json = $response['json'];
        if (isset($json['exception']) || isset($json['errorcode'])) {
            throw new RuntimeException('Moodle отклонил запрос Web Service.');
        }
        return $json;
    }
}
