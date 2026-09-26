<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        SessionSecurity::start();

        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    public static function validate(mixed $candidate): bool
    {
        if (!is_string($candidate) || $candidate === '') {
            return false;
        }

        SessionSecurity::start();
        $stored = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($stored) && hash_equals($stored, $candidate);
    }

    public static function rotate(): void
    {
        SessionSecurity::start();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    public static function input(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">';
    }
}
