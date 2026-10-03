<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use RuntimeException;

final class NativeTransferHttpClient implements ChannelTransferHttpClient
{
    /**
     * @param array<string,string> $headers
     * @return array{
     *     status:int,
     *     body:string,
     *     json:array<string,mixed>|null,
     *     headers:array<string,string>
     * }
     */
    public function request(
        string $method,
        string $url,
        ?string $body = null,
        array $headers = [],
    ): array {
        $method = strtoupper(trim($method));

        if (!in_array($method, ['POST', 'PUT'], true)) {
            throw new RuntimeException(
                'Для бинарной передачи разрешены только POST и PUT.'
            );
        }

        if (!str_starts_with($url, 'https://')) {
            throw new RuntimeException(
                'Бинарная передача во внешний канал требует HTTPS.'
            );
        }

        if (extension_loaded('curl')) {
            return $this->curlRequest(
                $method,
                $url,
                $body,
                $headers,
            );
        }

        if (
            filter_var(
                ini_get('allow_url_fopen'),
                FILTER_VALIDATE_BOOL,
            ) !== true
        ) {
            throw new RuntimeException(
                'Для бинарной передачи нужен ext-curl или allow_url_fopen.'
            );
        }

        return $this->streamRequest(
            $method,
            $url,
            $body,
            $headers,
        );
    }

    /**
     * @param array<string,string> $headers
     * @return array{
     *     status:int,
     *     body:string,
     *     json:array<string,mixed>|null,
     *     headers:array<string,string>
     * }
     */
    private function curlRequest(
        string $method,
        string $url,
        ?string $body,
        array $headers,
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException(
                'Не удалось инициализировать HTTP-клиент бинарной передачи.'
            );
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'ChurchCMS ExternalChannels/0.3',
            CURLOPT_HEADERFUNCTION => static function (
                mixed $curl,
                string $line,
            ) use (&$responseHeaders): int {
                $length = strlen($line);
                $line = trim($line);

                if ($line === '') {
                    return $length;
                }

                if (str_starts_with($line, 'HTTP/')) {
                    $responseHeaders = [];
                    return $length;
                }

                $separator = strpos($line, ':');
                if ($separator === false) {
                    return $length;
                }

                $name = strtolower(trim(substr($line, 0, $separator)));
                $value = trim(substr($line, $separator + 1));

                if ($name !== '') {
                    $responseHeaders[$name] = $value;
                }

                return $length;
            },
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo(
            $ch,
            CURLINFO_RESPONSE_CODE,
        );
        curl_close($ch);

        if (!is_string($response)) {
            throw new RuntimeException(
                'Ошибка бинарной передачи во внешний канал: '
                . $error
            );
        }

        return self::response(
            $status,
            $response,
            $responseHeaders,
        );
    }

    /**
     * @param array<string,string> $headers
     * @return array{
     *     status:int,
     *     body:string,
     *     json:array<string,mixed>|null,
     *     headers:array<string,string>
     * }
     */
    private function streamRequest(
        string $method,
        string $url,
        ?string $body,
        array $headers,
    ): array {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'timeout' => 120,
        ];

        if ($body !== null) {
            $options['content'] = $body;
        }

        $context = stream_context_create([
            'http' => $options,
        ]);
        $response = file_get_contents(
            $url,
            false,
            $context,
        );

        if (!is_string($response)) {
            throw new RuntimeException(
                'Ошибка бинарной передачи во внешний канал.'
            );
        }

        $status = 0;
        $responseHeaders = [];

        foreach ($http_response_header ?? [] as $line) {
            if (
                preg_match(
                    '/^HTTP\/\S+\s+(\d{3})/',
                    $line,
                    $match,
                ) === 1
            ) {
                $status = (int) $match[1];
                $responseHeaders = [];
                continue;
            }

            $separator = strpos($line, ':');
            if ($separator === false) {
                continue;
            }

            $name = strtolower(
                trim(substr($line, 0, $separator))
            );
            $value = trim(
                substr($line, $separator + 1)
            );

            if ($name !== '') {
                $responseHeaders[$name] = $value;
            }
        }

        return self::response(
            $status,
            $response,
            $responseHeaders,
        );
    }

    /**
     * @param array<string,string> $headers
     * @return array{
     *     status:int,
     *     body:string,
     *     json:array<string,mixed>|null,
     *     headers:array<string,string>
     * }
     */
    private static function response(
        int $status,
        string $body,
        array $headers,
    ): array {
        $json = null;

        try {
            $decoded = json_decode(
                $body,
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
            $json = is_array($decoded)
                ? $decoded
                : null;
        } catch (\JsonException) {
            $json = null;
        }

        return [
            'status' => $status,
            'body' => $body,
            'json' => $json,
            'headers' => $headers,
        ];
    }
}
