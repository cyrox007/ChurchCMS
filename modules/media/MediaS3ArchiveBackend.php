<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use InvalidArgumentException;
use RuntimeException;

final class MediaS3ArchiveBackend implements MediaArchiveBackend
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $bucket,
        private readonly string $region,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $prefix = 'churchcms-media',
    ) {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'Для S3-compatible Media archive требуется ext-curl.'
            );
        }

        $parts = parse_url($this->endpoint);
        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException(
                'S3 endpoint Media archive должен быть каноническим HTTPS URL.'
            );
        }

        foreach ([
            'bucket' => $this->bucket,
            'region' => $this->region,
            'access key' => $this->accessKey,
            'secret key' => $this->secretKey,
        ] as $name => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(
                    "S3 {$name} Media archive не настроен."
                );
            }
        }

        if (preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/D', $this->bucket) !== 1) {
            throw new InvalidArgumentException(
                'Некорректное имя S3 bucket Media archive.'
            );
        }
    }

    public function id(): string
    {
        return 's3';
    }

    public function put(
        string $sourcePath,
        string $objectKey,
        string $sha256,
        int $bytes,
        string $mimeType,
    ): void {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new InvalidArgumentException(
                'Локальный Media blob недоступен для архивирования.'
            );
        }

        $handle = fopen($sourcePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException(
                'Не удалось открыть Media blob для отправки в S3.'
            );
        }

        try {
            $headers = [
                'content-length' => (string) $bytes,
                'content-type' => $mimeType,
                'x-amz-content-sha256' => $sha256,
                'x-amz-meta-sha256' => $sha256,
            ];
            $this->request(
                method: 'PUT',
                objectKey: $objectKey,
                headers: $headers,
                uploadHandle: $handle,
                uploadBytes: $bytes,
                expectedStatus: [200, 201],
            );
        } finally {
            fclose($handle);
        }
    }

    public function fetch(
        string $objectKey,
        string $targetPath,
    ): void {
        $directory = dirname($targetPath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException(
                'Не удалось создать временный каталог восстановления Media.'
            );
        }

        $handle = fopen($targetPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException(
                'Не удалось открыть временный файл восстановления Media.'
            );
        }

        try {
            $this->request(
                method: 'GET',
                objectKey: $objectKey,
                downloadHandle: $handle,
                expectedStatus: [200],
            );
            fflush($handle);
        } catch (\Throwable $error) {
            @unlink($targetPath);
            throw $error;
        } finally {
            fclose($handle);
        }
    }

    public function metadata(string $objectKey): ?array
    {
        $response = $this->request(
            method: 'HEAD',
            objectKey: $objectKey,
            expectedStatus: [200, 404],
        );

        if ($response['status'] === 404) {
            return null;
        }

        $headers = $response['headers'];
        $bytes = isset($headers['content-length'])
            ? (int) $headers['content-length']
            : -1;
        $sha256 = isset($headers['x-amz-meta-sha256'])
            ? strtolower(trim($headers['x-amz-meta-sha256']))
            : null;

        return [
            'bytes' => $bytes,
            'sha256' => is_string($sha256) && preg_match('/^[a-f0-9]{64}$/D', $sha256) === 1
                ? $sha256
                : null,
        ];
    }

    public function delete(string $objectKey): void
    {
        $this->request(
            method: 'DELETE',
            objectKey: $objectKey,
            expectedStatus: [200, 204, 404],
        );
    }

    /**
     * @param array<string,string> $headers
     * @param resource|null $uploadHandle
     * @param resource|null $downloadHandle
     * @param list<int> $expectedStatus
     * @return array{status:int,headers:array<string,string>}
     */
    private function request(
        string $method,
        string $objectKey,
        array $headers = [],
        $uploadHandle = null,
        int $uploadBytes = 0,
        $downloadHandle = null,
        array $expectedStatus = [200],
    ): array {
        $method = strtoupper($method);
        $path = $this->canonicalPath($objectKey);
        $url = rtrim($this->endpoint, '/') . $path;
        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $payloadHash = $headers['x-amz-content-sha256'] ?? hash('sha256', '');
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        if (is_int($port) && $port !== 443) {
            $host .= ':' . $port;
        }

        $signedHeaders = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];
        foreach ($headers as $name => $value) {
            $signedHeaders[strtolower(trim($name))] = trim($value);
        }
        ksort($signedHeaders, SORT_STRING);

        $canonicalHeaders = '';
        foreach ($signedHeaders as $name => $value) {
            $canonicalHeaders .= $name . ':' . preg_replace('/\s+/', ' ', $value) . "\n";
        }
        $signedNames = implode(';', array_keys($signedHeaders));
        $canonicalRequest = implode("\n", [
            $method,
            $path,
            '',
            $canonicalHeaders,
            $signedNames,
            $payloadHash,
        ]);
        $scope = $date . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $now,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);
        $signature = hash_hmac(
            'sha256',
            $stringToSign,
            $this->signingKey($date),
        );
        $authorization = 'AWS4-HMAC-SHA256 Credential='
            . $this->accessKey . '/' . $scope
            . ', SignedHeaders=' . $signedNames
            . ', Signature=' . $signature;

        $responseHeaders = [];
        $curlHeaders = ['Authorization: ' . $authorization];
        foreach ($signedHeaders as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Не удалось создать S3 HTTP-запрос.');
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $position = strpos($line, ':');
                if ($position !== false) {
                    $name = strtolower(trim(substr($line, 0, $position)));
                    $value = trim(substr($line, $position + 1));
                    if ($name !== '') {
                        $responseHeaders[$name] = $value;
                    }
                }
                return $length;
            },
        ]);

        if (is_resource($uploadHandle)) {
            curl_setopt($curl, CURLOPT_UPLOAD, true);
            curl_setopt($curl, CURLOPT_INFILE, $uploadHandle);
            curl_setopt($curl, CURLOPT_INFILESIZE, $uploadBytes);
        } elseif (is_resource($downloadHandle)) {
            curl_setopt($curl, CURLOPT_FILE, $downloadHandle);
        } else {
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            if ($method === 'HEAD') {
                curl_setopt($curl, CURLOPT_NOBODY, true);
            }
        }

        try {
            $result = curl_exec($curl);
            if ($result === false) {
                throw new RuntimeException(
                    'S3 Media archive недоступен: ' . curl_error($curl)
                );
            }
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($curl);
        }

        if (!in_array($status, $expectedStatus, true)) {
            throw new RuntimeException(
                'S3 Media archive вернул неожиданный HTTP статус ' . $status . '.'
            );
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
        ];
    }

    private function canonicalPath(string $objectKey): string
    {
        $key = trim($objectKey, '/');
        if ($key === '' || str_contains($key, '..')) {
            throw new InvalidArgumentException(
                'Некорректный ключ объекта Media archive.'
            );
        }

        $prefix = trim($this->prefix, '/');
        $parts = array_filter(
            explode('/', ($prefix !== '' ? $prefix . '/' : '') . $key),
            static fn(string $part): bool => $part !== '',
        );
        $encoded = implode('/', array_map('rawurlencode', $parts));

        return '/' . rawurlencode($this->bucket) . '/' . $encoded;
    }

    private function signingKey(string $date): string
    {
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $regionKey = hash_hmac('sha256', $this->region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);

        return hash_hmac('sha256', 'aws4_request', $serviceKey, true);
    }
}
