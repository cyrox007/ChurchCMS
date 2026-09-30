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

    public static function notModified(
        string $etag,
        bool $immutable = false,
    ): never {
        self::assertEtag($etag);

        http_response_code(304);
        header('ETag: ' . $etag);
        header(
            'Cache-Control: public, max-age='
            . ($immutable ? '31536000, immutable' : '300')
        );
        header('X-Content-Type-Options: nosniff');
        exit;
    }

    public static function rangeNotSatisfiable(
        int $bytes,
        string $etag,
        bool $immutable = false,
    ): never {
        self::assertEtag($etag);

        if ($bytes < 0) {
            throw new InvalidArgumentException(
                'Invalid range response size.'
            );
        }

        http_response_code(416);
        header('Content-Range: bytes */' . $bytes);
        header('Accept-Ranges: bytes');
        header('Content-Length: 0');
        header('ETag: ' . $etag);
        header(
            'Cache-Control: public, max-age='
            . ($immutable ? '31536000, immutable' : '300')
        );
        header('X-Content-Type-Options: nosniff');
        exit;
    }

    public static function rangedFile(
        string $path,
        string $contentType,
        int $bytes,
        string $etag,
        ?HttpByteRange $range = null,
        bool $sendBody = true,
        bool $immutable = false,
    ): never {
        self::assertEtag($etag);

        if (
            $bytes < 0
            || $contentType === ''
            || str_contains($contentType, "\r")
            || str_contains($contentType, "\n")
            || !is_file($path)
            || is_link($path)
            || !is_readable($path)
        ) {
            throw new InvalidArgumentException(
                'Invalid ranged file response.'
            );
        }

        $actualBytes = filesize($path);
        if (
            !is_int($actualBytes)
            || $actualBytes !== $bytes
        ) {
            throw new InvalidArgumentException(
                'Ranged file response size mismatch.'
            );
        }

        $start = $range?->start ?? 0;
        $end = $range?->end ?? max(0, $bytes - 1);
        $length = $range?->length() ?? $bytes;

        http_response_code($range === null ? 200 : 206);
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . $length);
        header('ETag: ' . $etag);
        header('Accept-Ranges: bytes');

        if ($range !== null) {
            header(
                'Content-Range: bytes '
                . $start
                . '-'
                . $end
                . '/'
                . $bytes
            );
        }

        header(
            'Cache-Control: public, max-age='
            . ($immutable ? '31536000, immutable' : '300')
        );
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline');

        if (!$sendBody || $length === 0) {
            exit;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException(
                'Unable to open ranged file response.'
            );
        }

        try {
            if ($start > 0 && fseek($handle, $start) !== 0) {
                throw new InvalidArgumentException(
                    'Unable to seek ranged file response.'
                );
            }

            $remaining = $length;

            while (
                $remaining > 0
                && !feof($handle)
            ) {
                $chunk = fread(
                    $handle,
                    min(1048576, $remaining),
                );

                if ($chunk === false) {
                    throw new InvalidArgumentException(
                        'Unable to read ranged file response.'
                    );
                }

                if ($chunk === '') {
                    break;
                }

                $chunkLength = strlen($chunk);
                $remaining -= $chunkLength;
                echo $chunk;
            }

            if ($remaining !== 0) {
                throw new InvalidArgumentException(
                    'Ranged file response ended unexpectedly.'
                );
            }
        } finally {
            fclose($handle);
        }

        exit;
    }

    public static function file(
        string $path,
        string $contentType,
        int $bytes,
        string $etag,
        bool $sendBody = true,
        bool $immutable = false,
    ): never {
        self::assertEtag($etag);

        if (
            $bytes < 0
            || $contentType === ''
            || str_contains($contentType, "\r")
            || str_contains($contentType, "\n")
            || !is_file($path)
            || is_link($path)
            || !is_readable($path)
        ) {
            throw new InvalidArgumentException(
                'Invalid file response.'
            );
        }

        $actualBytes = filesize($path);
        if (
            !is_int($actualBytes)
            || $actualBytes !== $bytes
        ) {
            throw new InvalidArgumentException(
                'File response size mismatch.'
            );
        }

        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . $bytes);
        header('ETag: ' . $etag);
        header(
            'Cache-Control: public, max-age='
            . ($immutable ? '31536000, immutable' : '300')
        );
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline');

        if ($sendBody) {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                throw new InvalidArgumentException(
                    'Unable to open file response.'
                );
            }

            try {
                while (!feof($handle)) {
                    $chunk = fread($handle, 1048576);
                    if ($chunk === false) {
                        throw new InvalidArgumentException(
                            'Unable to read file response.'
                        );
                    }

                    if ($chunk !== '') {
                        echo $chunk;
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        exit;
    }

    private static function assertEtag(string $etag): void
    {
        if (
            preg_match(
                '/^"[a-f0-9]{64}"$/D',
                $etag,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid response ETag.'
            );
        }
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
