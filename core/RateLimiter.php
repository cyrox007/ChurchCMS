<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class RateLimiter
{
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @return array{allowed:bool,remaining:int,retry_after:int}
     */
    public function consume(string $key, int $limit, int $windowSeconds = 60): array
    {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);

        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create rate-limit storage.');
        }

        $bucket = intdiv(time(), $windowSeconds);
        $file = rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . hash('sha256', $key . ':' . $bucket)
            . '.json';

        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open rate-limit bucket.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock rate-limit bucket.');
            }

            $contents = stream_get_contents($handle);
            $data = is_string($contents) && $contents !== ''
                ? json_decode($contents, true)
                : null;

            $count = is_array($data) ? (int) ($data['count'] ?? 0) : 0;
            $count++;

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode(['count' => $count], JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        $remaining = max(0, $limit - $count);
        $retryAfter = max(1, (($bucket + 1) * $windowSeconds) - time());

        return [
            'allowed' => $count <= $limit,
            'remaining' => $remaining,
            'retry_after' => $retryAfter,
        ];
    }
}
