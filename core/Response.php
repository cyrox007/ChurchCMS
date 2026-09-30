<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use InvalidArgumentException;

final class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function text(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }

    public static function raw(
        string $body,
        int $status = 200,
        string $contentType = 'text/plain; charset=utf-8',
    ): never {
        if (
            $status < 100
            || $status > 599
            || $contentType === ''
            || str_contains($contentType, "\r")
            || str_contains($contentType, "\n")
        ) {
            throw new InvalidArgumentException(
                'Invalid raw response metadata.'
            );
        }

        http_response_code($status);
        header('Content-Type: ' . $contentType);
        header('Cache-Control: no-store');
        echo $body;
        exit;
    }

    public static function redirectLocal(string $path, int $status = 303): never
    {
        if (
            $path === ''
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, "\r")
            || str_contains($path, "\n")
        ) {
            throw new InvalidArgumentException('Redirect target must be a local absolute path.');
        }

        header('Location: ' . $path, true, $status);
        exit;
    }
}
