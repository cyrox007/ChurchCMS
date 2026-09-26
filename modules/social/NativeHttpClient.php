<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use RuntimeException;

final class NativeHttpClient
{
    /**
     * @param array<string,string|int|float|bool|null> $query
     * @param array<string,string> $headers
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    public function getJson(string $url, array $query = [], array $headers = []): array
    {
        if ($query !== []) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $this->request('GET', $url, null, $headers);
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,string> $headers
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    public function postJson(string $url, array $payload, array $headers = []): array
    {
        $body = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $headers['Content-Type'] = 'application/json';

        return $this->request('POST', $url, $body, $headers);
    }

    /**
     * @param array<string,string|int|float> $payload
     * @param array<string,string> $headers
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
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

    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    private function request(
        string $method,
        string $url,
        ?string $body,
        array $headers,
    ): array {
        if (!str_starts_with($url, 'https://')) {
            throw new RuntimeException('External channel requests require HTTPS.');
        }

        if (extension_loaded('curl')) {
            return $this->curlRequest($method, $url, $body, $headers);
        }

        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL) !== true) {
            throw new RuntimeException(
                'Either ext-curl or allow_url_fopen is required for external channel sync.'
            );
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'timeout' => 15,
        ];

        if ($body !== null) {
            $options['content'] = $body;
        }

        $context = stream_context_create(['http' => $options]);
        $response = file_get_contents($url, false, $context);

        if (!is_string($response)) {
            throw new RuntimeException('External channel API request failed.');
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        return $this->response($status, $response);
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    private function curlRequest(
        string $method,
        string $url,
        ?string $body,
        array $headers,
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize HTTP client.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'ChurchCMS ExternalChannels/0.2',
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
            throw new RuntimeException('External channel API request failed: ' . $error);
        }

        return $this->response($status, $response);
    }

    /** @return array{status:int,body:string,json:array<string,mixed>|null} */
    private function response(int $status, string $body): array
    {
        $json = null;

        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
            $json = is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            $json = null;
        }

        return [
            'status' => $status,
            'body' => $body,
            'json' => $json,
        ];
    }
}
