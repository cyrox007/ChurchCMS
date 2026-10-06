<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

use RuntimeException;

final class DistributionWebhookHttpClient
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string}
     */
    public function post(string $url, string $body, array $headers): array
    {
        $this->assertPublicHttpsEndpoint($url);

        if (!extension_loaded('curl')) {
            throw new RuntimeException('Для исходящих webhooks требуется ext-curl.');
        }

        $lines = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Не удалось инициализировать HTTP-клиент webhook.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'ChurchCMS DistributionWebhooks/0.1',
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($response)) {
            throw new RuntimeException('Ошибка доставки webhook: ' . $error);
        }

        return ['status' => $status, 'body' => $response];
    }

    public function assertPublicHttpsEndpoint(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new RuntimeException('Webhook endpoint должен использовать HTTPS.');
        }

        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new RuntimeException('Локальный webhook endpoint запрещён.');
        }

        $addresses = $this->resolve($host);
        if ($addresses === []) {
            throw new RuntimeException('Не удалось разрешить DNS webhook endpoint.');
        }

        foreach ($addresses as $address) {
            if (
                filter_var(
                    $address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
                ) === false
            ) {
                throw new RuntimeException('Webhook endpoint разрешился в приватный или reserved IP.');
            }
        }
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    $address = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
                    if ($address !== '') {
                        $addresses[$address] = true;
                    }
                }
            }
        }

        if ($addresses === []) {
            $ipv4 = @gethostbynamel($host);
            if (is_array($ipv4)) {
                foreach ($ipv4 as $address) {
                    if (is_string($address) && $address !== '') {
                        $addresses[$address] = true;
                    }
                }
            }
        }

        return array_keys($addresses);
    }
}
