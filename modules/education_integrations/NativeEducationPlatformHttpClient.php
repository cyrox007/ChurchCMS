<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationIntegrations;

use RuntimeException;

final class NativeEducationPlatformHttpClient implements EducationPlatformHttpClient
{
    public function getJson(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, null, $headers);
    }

    public function postForm(string $url, array $payload, array $headers = []): array
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        return $this->request(
            'POST',
            $url,
            http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
            $headers,
        );
    }

    /** @return array{status:int,body:string,json:array<string,mixed>|null} */
    private function request(string $method, string $url, ?string $body, array $headers): array
    {
        if (!str_starts_with($url, 'https://')) {
            throw new RuntimeException('Внешняя образовательная интеграция требует HTTPS.');
        }

        if (!extension_loaded('curl')) {
            throw new RuntimeException('Для внешних образовательных интеграций требуется ext-curl.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Не удалось инициализировать HTTP-клиент.');
        }

        $headerLines = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'ChurchCMS EducationIntegrations/0.1',
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($response)) {
            throw new RuntimeException('Ошибка запроса к образовательной платформе: ' . $error);
        }

        $json = null;
        try {
            $decoded = json_decode($response, true, 64, JSON_THROW_ON_ERROR);
            $json = is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            $json = null;
        }

        return ['status' => $status, 'body' => $response, 'json' => $json];
    }
}
