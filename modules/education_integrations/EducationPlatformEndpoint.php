<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationIntegrations;

use InvalidArgumentException;
use RuntimeException;

final class EducationPlatformEndpoint
{
    public static function baseUrl(string $value): string
    {
        $value = rtrim(trim($value), '/');
        if ($value === '' || strlen($value) > 2000 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Укажите корректный адрес образовательной платформы.');
        }

        $parts = parse_url($value);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new InvalidArgumentException('Адрес образовательной платформы должен использовать HTTPS.');
        }
        if (($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '') {
            throw new InvalidArgumentException('Учётные данные нельзя передавать в адресе платформы.');
        }
        if (($parts['query'] ?? '') !== '' || ($parts['fragment'] ?? '') !== '') {
            throw new InvalidArgumentException('Базовый адрес платформы не должен содержать query или fragment.');
        }

        $host = self::host($parts);
        if (filter_var($host, FILTER_VALIDATE_IP) !== false && !self::publicIp($host)) {
            throw new InvalidArgumentException('Внешняя образовательная платформа не может использовать приватный IP-адрес.');
        }

        return $value;
    }

    public static function assertPublicResolution(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Некорректный адрес внешней образовательной платформы.');
        }
        $host = self::host($parts);
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (!self::publicIp($host)) {
                throw new RuntimeException('Запрос к приватному или служебному IP-адресу запрещён.');
            }
            return;
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records) || $records === []) {
            throw new RuntimeException('Не удалось безопасно разрешить имя внешней образовательной платформы.');
        }
        foreach ($records as $record) {
            $ip = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ip === '' || !self::publicIp($ip)) {
                throw new RuntimeException('Домен внешней образовательной платформы указывает на приватный или служебный адрес.');
            }
        }
    }

    /** @param array<string,mixed> $parts */
    private static function host(array $parts): string
    {
        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('Локальный адрес нельзя использовать как внешнюю образовательную платформу.');
        }
        return $host;
    }

    private static function publicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
