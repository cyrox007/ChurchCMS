<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use DirectoryIterator;
use RuntimeException;
use Throwable;

final class UpdatePackageStager
{
    private const FORMAT = 'churchcms-update-v1';
    private const STAGE_ID_PATTERN = '/^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$/D';

    private UpdatePackageSignatureVerifier $signatureVerifier;

    public function __construct(
        private readonly string $root,
        private readonly string $stagingRoot,
        ?UpdatePackageSignatureVerifier $signatureVerifier = null,
    ) {
        $this->signatureVerifier = $signatureVerifier
            ?? new UpdatePackageSignatureVerifier();
    }

    /**
     * Проверяет пакет и копирует его в изолированный staging без изменения приложения.
     *
     * @return array{
     *     id:string,
     *     version:string,
     *     files:int,
     *     deleted_files:int,
     *     signature_key_id:string
     * }
     */
    public function stage(string $sourceDirectory): array
    {
        $source = $this->sourceDirectory($sourceDirectory);
        $stagingRoot = $this->prepareStagingRoot();

        if (
            $this->pathInside($stagingRoot, $source)
            || $this->pathInside($source, $stagingRoot)
        ) {
            throw new RuntimeException('Источник пакета и staging-каталог не должны быть вложены друг в друга.');
        }

        $manifest = $this->readManifest($source);
        $signature = $this->signatureVerifier->verify($manifest);
        $files = $this->validateManifest($manifest);
        $this->assertPackageFilesMatch($source, $files);
        $this->verifySourceFiles($source, $files);

        $id = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6));
        $temporary = $stagingRoot . DIRECTORY_SEPARATOR . '.staging-' . $id;
        $final = $stagingRoot . DIRECTORY_SEPARATOR . $id;

        if (!mkdir($temporary . '/payload', 0700, true) && !is_dir($temporary . '/payload')) {
            throw new RuntimeException('Не удалось подготовить временный staging-каталог.');
        }

        try {
            $this->copyManifest($source, $temporary);

            foreach ($files as $entry) {
                $sourcePath = $source
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
                $targetPath = $temporary
                    . DIRECTORY_SEPARATOR
                    . 'payload'
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);

                $this->copyVerifiedFile($sourcePath, $targetPath, $entry);
            }

            $this->verifyStagedFiles($temporary, $files);

            if (!rename($temporary, $final)) {
                throw new RuntimeException('Не удалось зафиксировать проверенный staging-пакет.');
            }

            return [
                'id' => $id,
                'version' => (string) $manifest['version'],
                'files' => count($files),
                'deleted_files' => count($manifest['deleted_files'] ?? []),
                'signature_key_id' => $signature['key_id'],
            ];
        } catch (Throwable $e) {
            $this->deleteTree($temporary);

            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException('Не удалось подготовить пакет обновления.', 0, $e);
        }
    }

    /**
     * Повторно проверяет уже подготовленный staging-пакет.
     *
     * @return array{
     *     id:string,
     *     version:string,
     *     path:string,
     *     files:array<string,array{path:string,bytes:int,sha256:string}>,
     *     deleted_files:list<string>,
     *     signature_key_id:string
     * }
     */
    public function inspect(string $stageId): array
    {
        if (preg_match(self::STAGE_ID_PATTERN, $stageId) !== 1) {
            throw new RuntimeException('Некорректный идентификатор staging-пакета.');
        }

        $stagingRoot = $this->prepareStagingRoot();
        $path = realpath($stagingRoot . DIRECTORY_SEPARATOR . $stageId);

        if (
            $path === false
            || !is_dir($path)
            || is_link($path)
            || dirname($path) !== $stagingRoot
        ) {
            throw new RuntimeException('Проверенный staging-пакет не найден.');
        }

        $manifest = $this->readManifest($path);
        $signature = $this->signatureVerifier->verify($manifest);
        $files = $this->validateManifest($manifest);
        $payload = $path . DIRECTORY_SEPARATOR . 'payload';

        if (!is_dir($payload) || is_link($payload)) {
            throw new RuntimeException('Payload staging-пакета отсутствует или небезопасен.');
        }

        $actual = $this->discoverPackageFiles($payload);
        $expected = array_keys($files);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        if ($actual !== $expected) {
            throw new RuntimeException('Набор файлов staging-пакета не совпадает с манифестом.');
        }

        $this->verifyStagedFiles($path, $files);

        $deleted = array_values($manifest['deleted_files'] ?? []);
        sort($deleted, SORT_STRING);

        return [
            'id' => $stageId,
            'version' => (string) $manifest['version'],
            'path' => $path,
            'files' => $files,
            'deleted_files' => $deleted,
            'signature_key_id' => $signature['key_id'],
        ];
    }

    /**
     * @return list<array{
     *     id:string,
     *     version:string,
     *     files:int,
     *     deleted_files:int,
     *     code_only:bool,
     *     ready:bool
     * }>
     */
    public function packages(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $root = $this->prepareStagingRoot();
        $entries = scandir($root);

        if ($entries === false) {
            throw new RuntimeException('Не удалось прочитать staging-каталог.');
        }

        rsort($entries, SORT_STRING);
        $packages = [];

        foreach ($entries as $entry) {
            if (
                count($packages) >= $limit
                || preg_match(self::STAGE_ID_PATTERN, $entry) !== 1
            ) {
                continue;
            }

            try {
                $stage = $this->inspect($entry);
                $paths = array_merge(
                    array_keys($stage['files']),
                    $stage['deleted_files'],
                );
                $codeOnly = true;

                foreach ($paths as $path) {
                    if (
                        str_starts_with($path, 'database/migrations/')
                        || preg_match(
                            '#^modules/[^/]+/migrations/#D',
                            $path,
                        ) === 1
                    ) {
                        $codeOnly = false;
                        break;
                    }
                }

                $packages[] = [
                    'id' => $entry,
                    'version' => $stage['version'],
                    'files' => count($stage['files']),
                    'deleted_files' => count($stage['deleted_files']),
                    'code_only' => $codeOnly,
                    'ready' => true,
                    'signature_key_id' =>
                        $stage['signature_key_id'],
                ];
            } catch (RuntimeException) {
                $packages[] = [
                    'id' => $entry,
                    'version' => '',
                    'files' => 0,
                    'deleted_files' => 0,
                    'code_only' => false,
                    'ready' => false,
                    'signature_key_id' => '',
                ];
            }
        }

        return $packages;
    }

    private function sourceDirectory(string $sourceDirectory): string
    {
        if (!$this->isAbsolutePath($sourceDirectory)) {
            throw new RuntimeException('Путь к пакету обновления должен быть абсолютным.');
        }

        if (is_link($sourceDirectory)) {
            throw new RuntimeException('Каталог пакета обновления не должен быть символической ссылкой.');
        }

        $source = realpath($sourceDirectory);
        $applicationRoot = realpath($this->root);

        if (
            $source === false
            || $applicationRoot === false
            || !is_dir($source)
            || $this->pathInside($source, $applicationRoot)
        ) {
            throw new RuntimeException('Пакет обновления должен находиться вне каталога ChurchCMS.');
        }

        return $source;
    }

    private function prepareStagingRoot(): string
    {
        if (!$this->isAbsolutePath($this->stagingRoot)) {
            throw new RuntimeException('Staging-каталог должен быть задан абсолютным путём.');
        }

        $applicationRoot = realpath($this->root);
        if ($applicationRoot === false) {
            throw new RuntimeException('Корневой каталог ChurchCMS недоступен.');
        }

        if ($this->pathInside($this->stagingRoot, $applicationRoot) || is_link($this->stagingRoot)) {
            throw new RuntimeException('Staging-каталог должен находиться вне корня ChurchCMS.');
        }

        if (
            !is_dir($this->stagingRoot)
            && !mkdir($this->stagingRoot, 0700, true)
            && !is_dir($this->stagingRoot)
        ) {
            throw new RuntimeException('Не удалось создать staging-каталог.');
        }

        @chmod($this->stagingRoot, 0700);
        $resolved = realpath($this->stagingRoot);

        if (
            $resolved === false
            || is_link($this->stagingRoot)
            || !is_writable($resolved)
            || $this->pathInside($resolved, $applicationRoot)
        ) {
            throw new RuntimeException('Staging-каталог недоступен для безопасной записи.');
        }

        return $resolved;
    }

    /**
     * @return array<string,mixed>
     */
    private function readManifest(string $source): array
    {
        $path = $source . DIRECTORY_SEPARATOR . 'manifest.json';

        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Манифест пакета обновления отсутствует или небезопасен.');
        }

        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            throw new RuntimeException('Не удалось прочитать манифест пакета обновления.');
        }

        $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Формат пакета обновления не поддерживается.');
        }

        return $manifest;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,array{path:string,bytes:int,sha256:string}>
     */
    private function validateManifest(array $manifest): array
    {
        $currentVersion = (string) Config::get('app.version', '');
        $fromVersion = trim((string) ($manifest['from_version'] ?? ''));
        $version = trim((string) ($manifest['version'] ?? ''));
        $phpMin = trim((string) ($manifest['php_min'] ?? ''));

        if ($fromVersion === '' || $fromVersion !== $currentVersion) {
            throw new RuntimeException('Пакет обновления не предназначен для текущей версии ChurchCMS.');
        }

        if (
            preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?$/D', $version) !== 1
            || version_compare($version, $currentVersion, '<=')
        ) {
            throw new RuntimeException('Целевая версия пакета обновления некорректна.');
        }

        if (
            $phpMin === ''
            || version_compare($phpMin, '8.3', '<')
            || version_compare(PHP_VERSION, $phpMin, '<')
        ) {
            throw new RuntimeException('Текущая версия PHP несовместима с пакетом обновления.');
        }

        $rawFiles = $manifest['files'] ?? null;
        if (!is_array($rawFiles) || $rawFiles === []) {
            throw new RuntimeException('Пакет обновления не содержит списка файлов.');
        }

        $files = [];
        foreach ($rawFiles as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Описание файла пакета обновления повреждено.');
            }

            $path = (string) ($entry['path'] ?? '');
            $bytes = $entry['bytes'] ?? null;
            $sha256 = (string) ($entry['sha256'] ?? '');

            if (
                !$this->safeApplicationPath($path)
                || !is_int($bytes)
                || $bytes < 0
                || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
                || isset($files[$path])
            ) {
                throw new RuntimeException('Манифест пакета содержит некорректный файл.');
            }

            $files[$path] = [
                'path' => $path,
                'bytes' => $bytes,
                'sha256' => $sha256,
            ];
        }

        $deleted = $manifest['deleted_files'] ?? [];
        if (!is_array($deleted)) {
            throw new RuntimeException('Список удаляемых файлов пакета некорректен.');
        }

        $seenDeleted = [];
        foreach ($deleted as $path) {
            if (
                !is_string($path)
                || !$this->safeApplicationPath($path)
                || isset($seenDeleted[$path])
                || isset($files[$path])
            ) {
                throw new RuntimeException('Манифест содержит некорректный путь удаления.');
            }

            $seenDeleted[$path] = true;
        }

        ksort($files, SORT_STRING);
        return $files;
    }

    private function safeApplicationPath(string $path): bool
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

    /**
     * @param array<string,array{path:string,bytes:int,sha256:string}> $files
     */
    private function assertPackageFilesMatch(string $source, array $files): void
    {
        $actual = $this->discoverPackageFiles($source);
        $expected = array_keys($files);

        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        if ($actual !== $expected) {
            throw new RuntimeException('Набор файлов пакета не совпадает с манифестом.');
        }
    }

    /**
     * @return list<string>
     */
    private function discoverPackageFiles(string $root, string $prefix = ''): array
    {
        $files = [];

        foreach (new DirectoryIterator($root) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            if ($entry->isLink()) {
                throw new RuntimeException('Символические ссылки в пакете обновления запрещены.');
            }

            $relative = $prefix === ''
                ? $entry->getFilename()
                : $prefix . '/' . $entry->getFilename();

            if ($prefix === '' && $relative === 'manifest.json') {
                continue;
            }

            if ($entry->isDir()) {
                $files = array_merge(
                    $files,
                    $this->discoverPackageFiles($entry->getPathname(), $relative),
                );
                continue;
            }

            if (!$entry->isFile() || !$this->safeApplicationPath($relative)) {
                throw new RuntimeException('Пакет содержит неподдерживаемый файл.');
            }

            $files[] = $relative;
        }

        return $files;
    }

    /**
     * @param array<string,array{path:string,bytes:int,sha256:string}> $files
     */
    private function verifySourceFiles(string $source, array $files): void
    {
        foreach ($files as $entry) {
            $path = $source
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
            $this->assertFileChecksum($path, $entry);
        }
    }

    private function copyManifest(string $source, string $targetRoot): void
    {
        $sourcePath = $source . DIRECTORY_SEPARATOR . 'manifest.json';
        $targetPath = $targetRoot . DIRECTORY_SEPARATOR . 'manifest.json';

        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Не удалось скопировать манифест в staging.');
        }

        @chmod($targetPath, 0600);
    }

    /**
     * @param array{path:string,bytes:int,sha256:string} $entry
     */
    private function copyVerifiedFile(string $source, string $target, array $entry): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Не удалось подготовить каталог staging-пакета.');
        }

        if (!copy($source, $target)) {
            throw new RuntimeException('Не удалось скопировать файл пакета в staging.');
        }

        @chmod($target, 0600);
        $this->assertFileChecksum($target, $entry);
    }

    /**
     * @param array<string,array{path:string,bytes:int,sha256:string}> $files
     */
    private function verifyStagedFiles(string $stage, array $files): void
    {
        foreach ($files as $entry) {
            $path = $stage
                . DIRECTORY_SEPARATOR
                . 'payload'
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
            $this->assertFileChecksum($path, $entry);
        }
    }

    /**
     * @param array{path:string,bytes:int,sha256:string} $entry
     */
    private function assertFileChecksum(string $path, array $entry): void
    {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Файл пакета отсутствует или небезопасен.');
        }

        $bytes = filesize($path);
        $sha256 = hash_file('sha256', $path);

        if ($bytes !== $entry['bytes'] || !is_string($sha256) || $sha256 !== $entry['sha256']) {
            throw new RuntimeException('Контрольная сумма файла пакета не совпала.');
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/D', $path) === 1
            || str_starts_with($path, '\\\\');
    }

    private function pathInside(string $candidate, string $root): bool
    {
        $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
        $root = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root);
        $candidate = rtrim($candidate, DIRECTORY_SEPARATOR);
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        if (DIRECTORY_SEPARATOR === '\\') {
            $candidate = strtolower($candidate);
            $root = strtolower($root);
        }

        return $candidate === $root
            || str_starts_with($candidate, $root . DIRECTORY_SEPARATOR);
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
