<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\Config;
use InvalidArgumentException;
use RuntimeException;

final class FederationDiscoveryClient
{
    private const MAX_BODY_BYTES = 65536;

    /**
     * @return array{
     *     base_url:string,
     *     instance_id:string,
     *     site_key:string,
     *     profile:string,
     *     organization:array{id:string,type:string,name:string},
     *     capabilities:list<string>
     * }
     */
    public function discover(string $baseUrl): array
    {
        $baseUrl = self::baseUrl($baseUrl);
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

        $target = $baseUrl . '/api/v1/federation/meta';
        [$status, $body] = $this->request(
            $target,
            $host,
            $addresses,
        );

        if ($status !== 200) {
            throw new RuntimeException(
                'Удалённый ChurchCMS вернул HTTP '
                . $status
                . ' при discovery.'
            );
        }

        try {
            $payload = json_decode(
                $body,
                true,
                32,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $error) {
            throw new RuntimeException(
                'Удалённый ChurchCMS вернул некорректный JSON.',
                0,
                $error,
            );
        }

        if (!is_array($payload)) {
            throw new RuntimeException(
                'Удалённый ChurchCMS вернул некорректный ответ.'
            );
        }

        $data = $payload['data'] ?? $payload;
        if (!is_array($data)) {
            throw new RuntimeException(
                'В discovery-ответе отсутствуют данные узла.'
            );
        }

        if (
            (string) ($data['protocol'] ?? '')
            !== 'churchcms-federation-v1'
        ) {
            throw new RuntimeException(
                'Удалённый сайт не поддерживает ожидаемый протокол ChurchCMS federation.'
            );
        }

        $instanceId = trim(
            (string) ($data['instance_id'] ?? '')
        );
        $siteKey = trim((string) ($data['site_key'] ?? ''));
        $profile = trim((string) ($data['profile'] ?? 'organization'));
        $organization = $data['organization'] ?? null;

        self::assertUuid($instanceId, 'instance ID');

        if (
            preg_match(
                '/^[a-zA-Z0-9_.-]{1,64}$/D',
                $siteKey,
            ) !== 1
        ) {
            throw new RuntimeException(
                'Удалённый ChurchCMS вернул некорректный site key.'
            );
        }

        if (
            $profile === ''
            || strlen($profile) > 64
            || !is_array($organization)
        ) {
            throw new RuntimeException(
                'Удалённый ChurchCMS вернул неполные сведения об организации.'
            );
        }

        $organizationId = trim(
            (string) ($organization['id'] ?? '')
        );
        $organizationType = trim(
            (string) ($organization['type'] ?? '')
        );
        $organizationName = trim(
            (string) ($organization['name'] ?? '')
        );

        self::assertUuid(
            $organizationId,
            'organization ID',
        );

        if (
            $organizationType === ''
            || strlen($organizationType) > 64
            || $organizationName === ''
            || self::length($organizationName) > 255
        ) {
            throw new RuntimeException(
                'Удалённый ChurchCMS вернул некорректную организацию.'
            );
        }

        $capabilities = [];
        foreach ($data['capabilities'] ?? [] as $capability) {
            if (
                is_string($capability)
                && preg_match(
                    '/^[a-z][a-z0-9_.:-]{1,63}$/D',
                    $capability,
                ) === 1
            ) {
                $capabilities[$capability] = true;
            }
        }

        return [
            'base_url' => $baseUrl,
            'instance_id' => $instanceId,
            'site_key' => $siteKey,
            'profile' => $profile,
            'organization' => [
                'id' => $organizationId,
                'type' => $organizationType,
                'name' => $organizationName,
            ],
            'capabilities' => array_keys($capabilities),
        ];
    }

    /**
     * @param list<string> $addresses
     * @return array{0:int,1:string}
     */
    private function request(
        string $url,
        string $host,
        array $addresses,
    ): array {
        if (extension_loaded('curl')) {
            return $this->curlRequest(
                $url,
                $host,
                $addresses,
            );
        }

        if (
            filter_var($host, FILTER_VALIDATE_IP) === false
            && strtolower($host) !== 'localhost'
        ) {
            throw new RuntimeException(
                'Для безопасного federation discovery по доменному имени требуется PHP-расширение cURL.'
            );
        }

        if (
            filter_var(
                ini_get('allow_url_fopen'),
                FILTER_VALIDATE_BOOL,
            ) !== true
        ) {
            throw new RuntimeException(
                'Для federation discovery требуется PHP-расширение cURL или allow_url_fopen.'
            );
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'User-Agent: ChurchCMS Federation/0.1',
                    'Connection: close',
                ]),
                'ignore_errors' => true,
                'timeout' => 8,
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
                'Не удалось получить federation metadata.'
            );
        }

        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new RuntimeException(
                'Federation metadata превышает допустимый размер.'
            );
        }

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
    ): array {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException(
                'Не удалось инициализировать federation discovery.'
            );
        }

        $body = '';
        $tooLarge = false;
        $parts = parse_url($url);
        $scheme = strtolower(
            (string) ($parts['scheme'] ?? 'https')
        );
        $port = (int) ($parts['port'] ?? (
            $scheme === 'https' ? 443 : 80
        ));

        $options = [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'ChurchCMS Federation/0.1',
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
                'Federation metadata превышает допустимый размер.'
            );
        }

        if ($ok === false) {
            throw new RuntimeException(
                'Не удалось получить federation metadata: '
                . $error
            );
        }

        return [$status, $body];
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
                'Discovery к служебным, link-local или зарезервированным адресам запрещён.'
            );
        }

        if ($hasPublic && $hasPrivate) {
            throw new InvalidArgumentException(
                'Discovery к узлу со смешанными публичными и частными адресами запрещён.'
            );
        }

        if ($hasPrivate && !$allowPrivate) {
            throw new InvalidArgumentException(
                'Discovery к локальным и частным адресам отключён настройкой federation.allow_private_discovery.'
            );
        }

        if ($scheme !== 'https' && !$hasPrivate) {
            throw new InvalidArgumentException(
                'Публичный federation discovery разрешён только по HTTPS.'
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

    private static function assertUuid(
        string $value,
        string $label,
    ): void {
        if (
            preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $value,
            ) !== 1
        ) {
            throw new RuntimeException(
                'Удалённый ChurchCMS вернул некорректный '
                . $label
                . '.'
            );
        }
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
