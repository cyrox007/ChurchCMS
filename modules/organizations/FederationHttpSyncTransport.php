<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\Config;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class FederationHttpSyncTransport implements FederationSyncTransport
{
    private const MAX_BODY_BYTES = 1048576;

    public function getJson(
        string $baseUrl,
        string $path,
        array $query,
        string $token,
    ): array {
        $baseUrl = self::baseUrl($baseUrl);
        $path = self::path($path);
        $token = self::token($token);

        $parts = parse_url($baseUrl);
        if (!is_array($parts)) {
            throw new InvalidArgumentException(
                'Некорректный адрес удалённого ChurchCMS-узла.'
            );
        }

        $host = trim((string) ($parts['host'] ?? ''), '[]');
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $addresses = self::resolveAddresses($host);
        $allowPrivate = Config::get(
            'federation.allow_private_discovery',
            false,
        ) === true;

        self::assertAddressesAllowed(
            $addresses,
            $scheme,
            $allowPrivate,
        );

        $queryString = http_build_query(
            $query,
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
        $url = $baseUrl . $path
            . ($queryString !== '' ? '?' . $queryString : '');

        [$status, $body] = $this->request(
            $url,
            $host,
            $addresses,
            $token,
        );

        if ($status !== 200) {
            throw new RuntimeException(
                'Удалённый ChurchCMS вернул HTTP '
                . $status
                . ' при синхронизации.'
            );
        }

        try {
            $payload = json_decode(
                $body,
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $error) {
            throw new RuntimeException(
                'Удалённый ChurchCMS вернул некорректный JSON синхронизации.',
                0,
                $error,
            );
        }

        if (!is_array($payload)) {
            throw new RuntimeException(
                'Удалённый ChurchCMS вернул некорректный ответ синхронизации.'
            );
        }

        return $payload;
    }

    /**
     * @param list<string> $addresses
     * @return array{0:int,1:string}
     */
    private function request(
        string $url,
        string $host,
        array $addresses,
        string $token,
    ): array {
        if (extension_loaded('curl')) {
            return $this->curlRequest(
                $url,
                $host,
                $addresses,
                $token,
            );
        }

        if (
            filter_var($host, FILTER_VALIDATE_IP) === false
            && strtolower($host) !== 'localhost'
        ) {
            throw new RuntimeException(
                'Для безопасного federation sync по доменному имени требуется PHP-расширение cURL.'
            );
        }

        if (
            filter_var(
                ini_get('allow_url_fopen'),
                FILTER_VALIDATE_BOOL,
            ) !== true
        ) {
            throw new RuntimeException(
                'Для federation sync требуется PHP-расширение cURL или allow_url_fopen.'
            );
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token,
                    'User-Agent: ChurchCMS Federation Sync/0.1',
                    'Connection: close',
                ]),
                'ignore_errors' => true,
                'timeout' => 10,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
        ]);

        $body = file_get_contents(
            $url,
            false,
            $context,
            0,
            self::MAX_BODY_BYTES + 1,
        );

        if (!is_string($body)) {
            throw new RuntimeException(
                'Не удалось получить данные federation sync.'
            );
        }

        self::assertBodySize($body);

        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (
                preg_match(
                    '/^HTTP\/\S+\s+(\d{3})/',
                    $line,
                    $match,
                ) === 1
            ) {
                $status = (int) $match[1];
            }
        }

        return [$status, $body];
    }

    /**
     * @param list<string> $addresses
     * @return array{0:int,1:string}
     */
    private function curlRequest(
        string $url,
        string $host,
        array $addresses,
        string $token,
    ): array {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException(
                'Не удалось инициализировать federation sync.'
            );
        }

        $body = '';
        $tooLarge = false;
        $parts = parse_url($url);
        $scheme = is_array($parts)
            ? strtolower((string) ($parts['scheme'] ?? ''))
            : '';
        $port = is_array($parts) && isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);

        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
                'User-Agent: ChurchCMS Federation Sync/0.1',
                'Connection: close',
            ],
            CURLOPT_WRITEFUNCTION => static function (
                mixed $curl,
                string $chunk,
            ) use (&$body, &$tooLarge): int {
                if (
                    strlen($body) + strlen($chunk)
                    > self::MAX_BODY_BYTES
                ) {
                    $tooLarge = true;
                    return 0;
                }

                $body .= $chunk;
                return strlen($chunk);
            },
        ];

        if (
            $host !== ''
            && filter_var(
                $host,
                FILTER_VALIDATE_IP,
            ) === false
            && $addresses !== []
        ) {
            $address = $addresses[0];
            if (str_contains($address, ':')) {
                $address = '[' . $address . ']';
            }

            $options[CURLOPT_RESOLVE] = [
                $host . ':' . $port . ':' . $address,
            ];
        }

        curl_setopt_array($handle, $options);

        $ok = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo(
            $handle,
            CURLINFO_RESPONSE_CODE,
        );
        curl_close($handle);

        if ($tooLarge) {
            throw new RuntimeException(
                'Ответ federation sync превышает допустимый размер.'
            );
        }

        if ($ok === false) {
            throw new RuntimeException(
                'Не удалось получить данные federation sync: '
                . $error
            );
        }

        return [$status, $body];
    }

    private static function assertBodySize(string $body): void
    {
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new RuntimeException(
                'Ответ federation sync превышает допустимый размер.'
            );
        }
    }

    private static function baseUrl(string $value): string
    {
        $value = rtrim(trim($value), '/');
        $parts = parse_url($value);

        if (
            strlen($value) > 500
            || !is_array($parts)
            || !in_array(
                strtolower((string) ($parts['scheme'] ?? '')),
                ['https', 'http'],
                true,
            )
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException(
                'Некорректный адрес удалённого ChurchCMS-узла.'
            );
        }

        return $value;
    }

    private static function path(string $value): string
    {
        if (
            preg_match(
                '#^/api/v1/partner/[a-z0-9/_-]+$#D',
                $value,
            ) !== 1
            || str_contains($value, '..')
        ) {
            throw new InvalidArgumentException(
                'Некорректный путь federation sync.'
            );
        }

        return $value;
    }

    private static function token(string $value): string
    {
        $value = trim($value);

        if (
            $value === ''
            || strlen($value) > 4096
            || str_contains($value, "\r")
            || str_contains($value, "\n")
        ) {
            throw new InvalidArgumentException(
                'Некорректный credential federation sync.'
            );
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function resolveAddresses(
        string $host,
    ): array {
        if (
            filter_var(
                $host,
                FILTER_VALIDATE_IP,
            ) !== false
        ) {
            return [$host];
        }

        if (strtolower($host) === 'localhost') {
            return ['127.0.0.1'];
        }

        $addresses = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record(
                $host,
                DNS_A | DNS_AAAA,
            );

            if (is_array($records)) {
                foreach ($records as $record) {
                    $address = $record['ip']
                        ?? $record['ipv6']
                        ?? null;

                    if (
                        is_string($address)
                        && filter_var(
                            $address,
                            FILTER_VALIDATE_IP,
                        ) !== false
                    ) {
                        $addresses[$address] = true;
                    }
                }
            }
        }

        if ($addresses === []) {
            $ipv4 = @gethostbynamel($host);
            if (is_array($ipv4)) {
                foreach ($ipv4 as $address) {
                    if (
                        filter_var(
                            $address,
                            FILTER_VALIDATE_IP,
                        ) !== false
                    ) {
                        $addresses[$address] = true;
                    }
                }
            }
        }

        if ($addresses === []) {
            throw new RuntimeException(
                'Не удалось разрешить адрес удалённого ChurchCMS-узла.'
            );
        }

        return array_keys($addresses);
    }

    /**
     * @param list<string> $addresses
     */
    private static function assertAddressesAllowed(
        array $addresses,
        string $scheme,
        bool $allowPrivate,
    ): void {
        $hasPublic = false;
        $hasPrivate = false;

        foreach ($addresses as $address) {
            if (self::isPublicAddress($address)) {
                $hasPublic = true;
                continue;
            }

            if (self::isAllowedPrivateAddress($address)) {
                $hasPrivate = true;
                continue;
            }

            throw new InvalidArgumentException(
                'Federation sync к служебным, link-local или зарезервированным адресам запрещён.'
            );
        }

        if ($hasPublic && $hasPrivate) {
            throw new InvalidArgumentException(
                'Federation sync к узлу со смешанными публичными и частными адресами запрещён.'
            );
        }

        if ($hasPrivate && !$allowPrivate) {
            throw new InvalidArgumentException(
                'Federation sync к локальным и частным адресам отключён настройкой federation.allow_private_discovery.'
            );
        }

        if ($scheme !== 'https' && !$hasPrivate) {
            throw new InvalidArgumentException(
                'Публичный federation sync разрешён только по HTTPS.'
            );
        }
    }

    private static function isPublicAddress(
        string $address,
    ): bool {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE
            | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private static function isAllowedPrivateAddress(
        string $address,
    ): bool {
        if (
            $address === '::1'
            || str_starts_with(
                strtolower($address),
                'fc',
            )
            || str_starts_with(
                strtolower($address),
                'fd',
            )
        ) {
            return true;
        }

        if (
            filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4,
            ) === false
        ) {
            return false;
        }

        $value = ip2long($address);
        if ($value === false) {
            return false;
        }

        $unsigned = (int) sprintf('%u', $value);

        return self::inIpv4Range(
            $unsigned,
            '127.0.0.0',
            '127.255.255.255',
        )
            || self::inIpv4Range(
                $unsigned,
                '10.0.0.0',
                '10.255.255.255',
            )
            || self::inIpv4Range(
                $unsigned,
                '172.16.0.0',
                '172.31.255.255',
            )
            || self::inIpv4Range(
                $unsigned,
                '192.168.0.0',
                '192.168.255.255',
            );
    }

    private static function inIpv4Range(
        int $value,
        string $start,
        string $end,
    ): bool {
        $startValue = ip2long($start);
        $endValue = ip2long($end);

        if (
            $startValue === false
            || $endValue === false
        ) {
            return false;
        }

        $startUnsigned = (int) sprintf(
            '%u',
            $startValue,
        );
        $endUnsigned = (int) sprintf(
            '%u',
            $endValue,
        );

        return $value >= $startUnsigned
            && $value <= $endUnsigned;
    }
}
