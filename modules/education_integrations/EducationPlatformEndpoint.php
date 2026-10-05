<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationIntegrations;

use InvalidArgumentException;

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

        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('Локальный адрес нельзя использовать как внешнюю образовательную платформу.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false && !self::publicIp($host)) {
            throw new InvalidArgumentException('Внешняя образовательная платформа не может использовать приватный IP-адрес.');
        }

        return $value;
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
