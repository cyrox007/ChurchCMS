<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class SessionSecurity
{
    private static bool $configured = false;

    public static function configure(): void
    {
        if (self::$configured) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session security must be configured before session_start().');
        }

        $name = (string) Config::get('session.name', 'churchcms_session');
        $lifetime = max(0, min(86400 * 30, (int) Config::get('session.lifetime_seconds', 28800)));
        $sameSite = (string) Config::get('session.same_site', 'Lax');
        if (!in_array($sameSite, ['Lax', 'Strict'], true)) {
            $sameSite = 'Lax';
        }

        $secure = self::isSecureRequest();

        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $name)) {
            throw new RuntimeException('Invalid session name.');
        }

        session_name($name);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.cookie_samesite', $sameSite);
        ini_set('session.cookie_lifetime', (string) $lifetime);
        ini_set('session.gc_maxlifetime', (string) max($lifetime, (int) ini_get('session.gc_maxlifetime')));

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);

        self::$configured = true;
    }

    public static function start(): void
    {
        if (!self::$configured) {
            self::configure();
        }

        if (session_status() === PHP_SESSION_NONE && !session_start()) {
            throw new RuntimeException('Unable to start session.');
        }
    }

    public static function regenerate(): void
    {
        self::start();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Unable to regenerate session id.');
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];

        if (!headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string) ($params['path'] ?? '/'),
                'domain' => (string) ($params['domain'] ?? ''),
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => (string) ($params['samesite'] ?? 'Lax'),
            ]);
        }

        session_destroy();
    }

    private static function isSecureRequest(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https === 'on' || $https === '1') {
            return true;
        }

        $url = (string) Config::get('app.url', '');
        return str_starts_with(strtolower($url), 'https://');
    }
}
