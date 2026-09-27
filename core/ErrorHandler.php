<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    private const FATAL_TYPES = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
    ];

    private static string $root = '';
    private static ?string $requestId = null;
    private static bool $responded = false;

    public static function register(string $root): void
    {
        self::$root = rtrim($root, DIRECTORY_SEPARATOR);
        self::$requestId = self::newRequestId();

        ini_set('display_errors', '0');
        error_reporting(E_ALL);

        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function requestId(): string
    {
        return self::$requestId ??= self::newRequestId();
    }

    public static function handleException(Throwable $error): never
    {
        self::report($error);
        self::respond($error);
    }

    public static function handleShutdown(): void
    {
        if (self::$responded) {
            return;
        }

        $last = error_get_last();
        if (
            !is_array($last)
            || !in_array((int) ($last['type'] ?? 0), self::FATAL_TYPES, true)
        ) {
            return;
        }

        $error = new ErrorException(
            (string) ($last['message'] ?? 'Fatal error'),
            0,
            (int) ($last['type'] ?? E_ERROR),
            (string) ($last['file'] ?? ''),
            (int) ($last['line'] ?? 0),
        );

        self::report($error);
        self::respond($error);
    }

    private static function report(Throwable $error): void
    {
        $requestId = self::requestId();
        $message = self::singleLine($error->getMessage(), 2000);
        $trace = substr($error->getTraceAsString(), 0, 12000);

        $record = sprintf(
            "[%s] request_id=%s class=%s message=%s file=%s line=%d\n%s\n",
            gmdate(DATE_ATOM),
            $requestId,
            $error::class,
            $message,
            $error->getFile(),
            $error->getLine(),
            $trace,
        );

        error_log(
            'ChurchCMS request_id='
            . $requestId
            . ' '
            . $error::class
            . ': '
            . $message
        );

        $path = self::logPath();
        if ($path === null) {
            return;
        }

        if (file_put_contents($path, $record, FILE_APPEND | LOCK_EX) !== false) {
            @chmod($path, 0640);
        }
    }

    private static function respond(Throwable $error): never
    {
        self::$responded = true;
        $requestId = self::requestId();
        $debug = self::debugEnabled();

        if (!headers_sent()) {
            http_response_code(500);
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            header('X-Request-ID: ' . $requestId);
        }

        if (self::wantsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            $payload = [
                'error' => 'internal_server_error',
                'message' => 'Внутренняя ошибка сервера.',
                'request_id' => $requestId,
            ];

            if ($debug) {
                $payload['debug'] = [
                    'class' => $error::class,
                    'message' => $error->getMessage(),
                    'file' => $error->getFile(),
                    'line' => $error->getLine(),
                ];
            }

            echo json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE,
            );
            exit(1);
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $debugBlock = '';
        if ($debug) {
            $debugBlock = '<details><summary>Отладочная информация</summary><pre>'
                . self::escape(
                    $error::class
                    . ': '
                    . $error->getMessage()
                    . "\n"
                    . $error->getFile()
                    . ':'
                    . $error->getLine()
                )
                . '</pre></details>';
        }

        echo '<!doctype html>'
            . '<html lang="ru"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>Ошибка — ChurchCMS</title></head>'
            . '<body><main><h1>Не удалось открыть страницу</h1>'
            . '<p>Произошла внутренняя ошибка. Повторите попытку позже.</p>'
            . '<p>Код обращения: <code>'
            . self::escape($requestId)
            . '</code></p>'
            . $debugBlock
            . '</main></body></html>';

        exit(1);
    }

    private static function wantsJson(): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        if ($path === '/health' || str_starts_with($path, '/api/')) {
            return true;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        return str_contains($accept, 'application/json')
            || str_starts_with($contentType, 'application/json');
    }

    private static function debugEnabled(): bool
    {
        return class_exists(Config::class, false)
            && Config::get('app.debug', false) === true;
    }

    private static function logPath(): ?string
    {
        if (self::$root === '') {
            return null;
        }

        $directory = self::$root
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'logs';

        if (
            !is_dir($directory)
            || is_link($directory)
            || !is_writable($directory)
        ) {
            return null;
        }

        return $directory . DIRECTORY_SEPARATOR . 'error.log';
    }

    private static function singleLine(string $value, int $limit): string
    {
        $value = str_replace(
            ["\r", "\n", "\0"],
            [' ', ' ', ''],
            $value,
        );

        return substr($value, 0, $limit);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
    }

    private static function newRequestId(): string
    {
        try {
            return bin2hex(random_bytes(12));
        } catch (Throwable) {
            return substr(
                hash(
                    'sha256',
                    microtime(true)
                    . ':'
                    . getmypid()
                    . ':'
                    . memory_get_usage(),
                ),
                0,
                24,
            );
        }
    }
}
