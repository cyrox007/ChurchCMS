<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class ApiResponse
{
    public static function success(
        mixed $data,
        array $meta = [],
        int $status = 200,
        ?int $cacheSeconds = null,
    ): never {
        self::headers($cacheSeconds);

        echo json_encode([
            'api_version' => (string) Config::get('api.version', 'v1'),
            'data' => $data,
            'meta' => ['request_id' => self::requestId()] + $meta,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(
        string $code,
        string $message,
        int $status,
        array $details = [],
    ): never {
        self::headers(null);

        echo json_encode([
            'api_version' => (string) Config::get('api.version', 'v1'),
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => ['request_id' => self::requestId()],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function headers(?int $cacheSeconds): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-ChurchCMS-API-Version: ' . (string) Config::get('api.version', 'v1'));
        header('X-Request-ID: ' . self::requestId());

        if ($cacheSeconds !== null && $cacheSeconds > 0) {
            header('Cache-Control: public, max-age=' . $cacheSeconds);
        } else {
            header('Cache-Control: no-store');
        }
    }

    private static function requestId(): string
    {
        static $id = null;

        if ($id === null) {
            $id = bin2hex(random_bytes(16));
        }

        return $id;
    }
}
