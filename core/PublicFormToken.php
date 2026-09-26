<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class PublicFormToken
{
    public static function issue(string $scope, int $ttlSeconds = 7200): string
    {
        self::assertScope($scope);

        $expires = time() + max(300, min(86400, $ttlSeconds));
        $payload = $expires . ':' . $scope;
        $signature = hash_hmac('sha256', $payload, self::key());

        return self::base64UrlEncode($expires . ':' . $signature);
    }

    public static function validate(string $scope, mixed $token): bool
    {
        self::assertScope($scope);

        if (!is_string($token) || $token === '') {
            return false;
        }

        $decoded = self::base64UrlDecode($token);
        if ($decoded === null) {
            return false;
        }

        [$expiresRaw, $signature] = array_pad(explode(':', $decoded, 2), 2, '');
        if (!ctype_digit($expiresRaw) || strlen($signature) !== 64) {
            return false;
        }

        $expires = (int) $expiresRaw;
        if ($expires < time() || $expires > time() + 86400 + 60) {
            return false;
        }

        $expected = hash_hmac('sha256', $expires . ':' . $scope, self::key());

        return hash_equals($expected, $signature);
    }

    private static function key(): string
    {
        $encoded = trim((string) Config::get('security.secret_key', ''));
        $key = base64_decode($encoded, true);

        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException(
                'security.secret_key must be configured before public forms are enabled.'
            );
        }

        return $key;
    }

    private static function assertScope(string $scope): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,80}$/D', $scope) !== 1) {
            throw new RuntimeException('Invalid public form token scope.');
        }
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(
            strtr($value . str_repeat('=', $padding), '-_', '+/'),
            true,
        );

        return is_string($decoded) ? $decoded : null;
    }
}
