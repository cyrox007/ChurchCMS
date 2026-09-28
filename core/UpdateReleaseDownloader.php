<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use DirectoryIterator;
use JsonException;
use RuntimeException;
use Throwable;

final class UpdateReleaseDownloader
{
    private const MAX_MANIFEST_BYTES = 1048576;
    private const MAX_FILE_BYTES = 67108864;
    private const MAX_PACKAGE_BYTES = 268435456;

    public function __construct(
        private readonly string $root,
        private readonly string $stagingRoot,
    ) {
    }

    /**
     * Скачивает подписанный пакет из доверенного HTTPS-источника
     * и передаёт его обычному staging после локальной проверки.
     *
     * @return array{
     *     id:string,
     *     version:string,
     *     files:int,
     *     deleted_files:int,
     *     signature_key_id:string
     * }
     */
    public function downloadAndStage(string $manifestUrl): array
    {
        $manifestUrl = $this->manifestUrl($manifestUrl);
        $endpoint = $this->endpoint($manifestUrl);

        $temporary = rtrim(
            sys_get_temp_dir(),
            DIRECTORY_SEPARATOR,
        ) . DIRECTORY_SEPARATOR
            . 'churchcms-update-download-'
            . bin2hex(random_bytes(8));

        if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) {
            throw new RuntimeException(
                'Не удалось создать временный каталог загрузки обновления.'
            );
        }

        try {
            $manifestRaw = $this->downloadText(
                $manifestUrl,
                $endpoint,
                self::MAX_MANIFEST_BYTES,
            );
            $manifest = $this->decodeManifest($manifestRaw);

            // Подпись проверяется до загрузки payload:
            // недоверенный источник не заставляет установку скачивать файлы.
            (new UpdatePackageSignatureVerifier())
                ->verify($manifest);

            $entries = $this->downloadEntries($manifest);
            $totalBytes = 0;

            file_put_contents(
                $temporary . DIRECTORY_SEPARATOR . 'manifest.json',
                $manifestRaw,
                LOCK_EX,
            );

            foreach ($entries as $entry) {
                $totalBytes += $entry['bytes'];
                if ($totalBytes > self::MAX_PACKAGE_BYTES) {
                    throw new RuntimeException(
                        'Размер пакета обновления превышает допустимый предел.'
                    );
                }

                $url = $this->payloadUrl(
                    $manifestUrl,
                    $entry['path'],
                );
                $target = $temporary
                    . DIRECTORY_SEPARATOR
                    . str_replace(
                        '/',
                        DIRECTORY_SEPARATOR,
                        $entry['path'],
                    );

                $this->downloadFile(
                    $url,
                    $endpoint,
                    $target,
                    $entry['bytes'],
                    $entry['sha256'],
                );
            }

            return (new UpdatePackageStager(
                $this->root,
                $this->stagingRoot,
            ))->stage($temporary);
        } finally {
            $this->deleteTree($temporary);
        }
    }

    private function manifestUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || isset($parts['query'])
        ) {
            throw new RuntimeException(
                'Источник обновления должен быть точным HTTPS URL без credentials, query и fragment.'
            );
        }

        $path = (string) ($parts['path'] ?? '');
        if (
            $path === ''
            || !str_ends_with(strtolower($path), '/manifest.json')
        ) {
            throw new RuntimeException(
                'URL источника обновления должен указывать на manifest.json.'
            );
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : 443;
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException(
                'Некорректный порт источника обновления.'
            );
        }

        return $url;
    }

    /**
     * @return array{host:string,port:int,addresses:list<string>}
     */
    private function endpoint(string $url): array
    {
        $parts = parse_url($url);
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        $port = isset($parts['port']) ? (int) $parts['port'] : 443;

        $addresses = $this->resolveAddresses($host);
        foreach ($addresses as $address) {
            if (!$this->isPublicAddress($address)) {
                throw new RuntimeException(
                    'Источник обновления не может указывать на частный, локальный или служебный адрес.'
                );
            }
        }

        return [
            'host' => $host,
            'port' => $port,
            'addresses' => $addresses,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record(
                $host,
                DNS_A | DNS_AAAA,
            );

            if (is_array($records)) {
                foreach ($records as $record) {
                    $address = $record['ip']
                        ?? $record['ipv6']
                        ?? null;
                    if (is_string($address) && $address !== '') {
                        $addresses[$address] = true;
                    }
                }
            }
        }

        if ($addresses === []) {
            $ipv4 = @gethostbynamel($host);
            if (is_array($ipv4)) {
                foreach ($ipv4 as $address) {
                    if (is_string($address) && $address !== '') {
                        $addresses[$address] = true;
                    }
                }
            }
        }

        if ($addresses === []) {
            throw new RuntimeException(
                'Не удалось определить адрес источника обновления.'
            );
        }

        return array_keys($addresses);
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return list<array{path:string,bytes:int,sha256:string}>
     */
    private function downloadEntries(array $manifest): array
    {
        if (($manifest['format'] ?? null) !== 'churchcms-update-v1') {
            throw new RuntimeException(
                'Формат удалённого пакета обновления не поддерживается.'
            );
        }

        $files = $manifest['files'] ?? null;
        if (!is_array($files) || $files === []) {
            throw new RuntimeException(
                'Удалённый манифест не содержит файлов обновления.'
            );
        }

        $entries = [];
        $seen = [];

        foreach ($files as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException(
                    'Удалённый манифест содержит повреждённое описание файла.'
                );
            }

            $path = (string) ($entry['path'] ?? '');
            $bytes = $entry['bytes'] ?? null;
            $sha256 = strtolower(
                trim((string) ($entry['sha256'] ?? ''))
            );

            if (
                !$this->safePath($path)
                || !is_int($bytes)
                || $bytes < 0
                || $bytes > self::MAX_FILE_BYTES
                || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
                || isset($seen[$path])
            ) {
                throw new RuntimeException(
                    'Удалённый манифест содержит небезопасный файл.'
                );
            }

            $seen[$path] = true;
            $entries[] = [
                'path' => $path,
                'bytes' => $bytes,
                'sha256' => $sha256,
            ];
        }

        return $entries;
    }

    private function safePath(string $path): bool
    {
        if (
            $path === ''
            || strlen($path) > 240
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || str_contains($path, "\0")
            || str_contains($path, '\\')
        ) {
            return false;
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        if ($segments[0] === '.git' || $segments[0] === 'storage') {
            return false;
        }

        return $path !== 'config/local.php';
    }

    private function payloadUrl(
        string $manifestUrl,
        string $path,
    ): string {
        $base = substr(
            $manifestUrl,
            0,
            strrpos($manifestUrl, '/') + 1,
        );

        $encoded = implode(
            '/',
            array_map(
                static fn(string $segment): string =>
                    rawurlencode($segment),
                explode('/', $path),
            ),
        );

        return $base . $encoded;
    }

    /**
     * @param array{host:string,port:int,addresses:list<string>} $endpoint
     */
    private function downloadText(
        string $url,
        array $endpoint,
        int $limit,
    ): string {
        $buffer = '';
        $this->request(
            $url,
            $endpoint,
            $limit,
            static function (string $chunk) use (&$buffer): void {
                $buffer .= $chunk;
            },
        );

        return $buffer;
    }

    /**
     * @param array{host:string,port:int,addresses:list<string>} $endpoint
     */
    private function downloadFile(
        string $url,
        array $endpoint,
        string $target,
        int $expectedBytes,
        string $expectedSha256,
    ): void {
        $directory = dirname($target);
        if (
            !is_dir($directory)
            && !mkdir($directory, 0700, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Не удалось подготовить каталог загружаемого пакета.'
            );
        }

        $handle = fopen($target, 'xb');
        if ($handle === false) {
            throw new RuntimeException(
                'Не удалось создать файл загружаемого пакета.'
            );
        }

        try {
            $written = 0;
            $this->request(
                $url,
                $endpoint,
                $expectedBytes + 1,
                static function (string $chunk) use (
                    $handle,
                    &$written,
                ): void {
                    $length = strlen($chunk);
                    $result = fwrite($handle, $chunk);
                    if ($result !== $length) {
                        throw new RuntimeException(
                            'Не удалось записать загруженный файл обновления.'
                        );
                    }

                    $written += $length;
                },
            );
        } finally {
            fclose($handle);
        }

        @chmod($target, 0600);

        $actualBytes = filesize($target);
        $actualSha256 = hash_file('sha256', $target);

        if (
            $actualBytes !== $expectedBytes
            || !is_string($actualSha256)
            || !hash_equals($expectedSha256, $actualSha256)
        ) {
            throw new RuntimeException(
                'Размер или SHA-256 загруженного файла не совпадает с подписанным манифестом.'
            );
        }
    }

    /**
     * @param array{host:string,port:int,addresses:list<string>} $endpoint
     * @param callable(string):void $consumer
     */
    private function request(
        string $url,
        array $endpoint,
        int $limit,
        callable $consumer,
    ): void {
        if (!extension_loaded('curl')) {
            throw new RuntimeException(
                'Для безопасной сетевой загрузки обновлений требуется PHP-расширение cURL.'
            );
        }

        $received = 0;
        $lastError = null;

        foreach ($endpoint['addresses'] as $address) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException(
                    'Не удалось инициализировать загрузку обновления.'
                );
            }

            $resolve = $endpoint['host']
                . ':' . $endpoint['port']
                . ':' . $address;

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'ChurchCMS Updater/0.1',
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json, application/octet-stream',
                    'Connection: close',
                ],
                CURLOPT_RESOLVE => [$resolve],
                CURLOPT_WRITEFUNCTION => static function (
                    $curl,
                    string $chunk,
                ) use (
                    &$received,
                    $limit,
                    $consumer,
                ): int {
                    $length = strlen($chunk);
                    $received += $length;

                    if ($received > $limit) {
                        return 0;
                    }

                    $consumer($chunk);
                    return $length;
                },
            ]);

            $ok = curl_exec($ch);
            $status = (int) curl_getinfo(
                $ch,
                CURLINFO_RESPONSE_CODE,
            );
            $error = curl_error($ch);
            curl_close($ch);

            if ($ok !== false && $status === 200) {
                return;
            }

            if ($received > $limit) {
                throw new RuntimeException(
                    'Ответ источника обновления превышает допустимый размер.'
                );
            }

            if ($received > 0) {
                throw new RuntimeException(
                    'Соединение с источником обновления прервалось во время передачи файла.'
                );
            }

            $lastError = $status > 0
                ? 'HTTP ' . $status
                : ($error !== '' ? $error : 'неизвестная ошибка');
        }

        throw new RuntimeException(
            'Не удалось получить файл обновления: '
            . $lastError
            . '.'
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeManifest(string $raw): array
    {
        try {
            $manifest = json_decode(
                $raw,
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $error) {
            throw new RuntimeException(
                'Источник обновления вернул повреждённый manifest.json.',
                0,
                $error,
            );
        }

        if (!is_array($manifest)) {
            throw new RuntimeException(
                'Источник обновления вернул некорректный manifest.json.'
            );
        }

        return $manifest;
    }

    private function deleteTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (new DirectoryIterator($path) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            $this->deleteTree($entry->getPathname());
        }

        @rmdir($path);
    }
}
