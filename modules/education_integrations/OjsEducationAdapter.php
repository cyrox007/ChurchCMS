<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationIntegrations;

use InvalidArgumentException;
use RuntimeException;

final class OjsEducationAdapter implements EducationPlatformAdapter
{
    private string $baseUrl;
    private string $token;
    private string $journalPath;

    public function __construct(
        string $baseUrl,
        string $token,
        string $journalPath,
        private readonly EducationPlatformHttpClient $http,
    ) {
        $this->baseUrl = EducationPlatformEndpoint::baseUrl($baseUrl);
        $this->token = trim($token);
        if ($this->token === '' || strlen($this->token) > 4096) {
            throw new InvalidArgumentException('Укажите API token OJS.');
        }

        $this->journalPath = trim($journalPath, " /\t\n\r\0\x0B");
        if ($this->journalPath !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $this->journalPath) !== 1) {
            throw new InvalidArgumentException('Некорректный путь журнала OJS.');
        }
    }

    public function id(): string
    {
        return 'ojs';
    }

    public function label(): string
    {
        return 'Open Journal Systems';
    }

    public function testConnection(): array
    {
        $contexts = $this->contexts();
        return [
            'ok' => true,
            'message' => 'Соединение с OJS проверено.',
            'metadata' => [
                'contexts_count' => count($contexts),
                'journal_path' => $this->journalPath,
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function contexts(): array
    {
        return $this->items($this->baseUrl . '/_/api/v1/contexts');
    }

    /** @return list<array<string,mixed>> */
    public function submissions(): array
    {
        if ($this->journalPath === '') {
            throw new InvalidArgumentException('Для получения заявок укажите путь журнала OJS.');
        }
        return $this->items(
            $this->baseUrl . '/' . rawurlencode($this->journalPath) . '/api/v1/submissions'
        );
    }

    /** @return list<array<string,mixed>> */
    private function items(string $url): array
    {
        $response = $this->http->getJson(
            $url,
            ['Authorization' => 'Bearer ' . $this->token],
        );
        if ($response['status'] < 200 || $response['status'] >= 300 || $response['json'] === null) {
            throw new RuntimeException('OJS API недоступен или вернул некорректный ответ.');
        }
        $json = $response['json'];
        $items = $json['items'] ?? $json;
        if (!is_array($items) || !array_is_list($items)) {
            throw new RuntimeException('OJS вернул неожиданный формат списка.');
        }
        return array_values(array_filter($items, 'is_array'));
    }
}
