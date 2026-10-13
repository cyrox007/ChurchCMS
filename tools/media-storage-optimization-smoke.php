#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Media\MediaArchiveBackend;
use ChurchCMS\Modules\Media\MediaArchiveRepository;
use ChurchCMS\Modules\Media\MediaArchiveService;
use ChurchCMS\Modules\Media\MediaBlobStorage;

require dirname(__DIR__) . '/core.php';

final class MediaArchiveSmokeBackend implements MediaArchiveBackend
{
    /** @var array<string,array{content:string,sha256:string,bytes:int}> */
    private array $objects = [];

    public bool $corruptFetch = false;

    public function id(): string
    {
        return 'smoke';
    }

    public function put(
        string $sourcePath,
        string $objectKey,
        string $sha256,
        int $bytes,
        string $mimeType,
    ): void {
        $content = file_get_contents($sourcePath);
        if (!is_string($content)) {
            throw new RuntimeException('Smoke archive не смог прочитать исходный blob.');
        }
        $this->objects[$objectKey] = [
            'content' => $content,
            'sha256' => $sha256,
            'bytes' => $bytes,
        ];
    }

    public function fetch(string $objectKey, string $targetPath): void
    {
        $object = $this->objects[$objectKey] ?? null;
        if ($object === null) {
            throw new RuntimeException('Smoke archive не содержит объект.');
        }

        $content = $this->corruptFetch
            ? $object['content'] . 'corrupt'
            : $object['content'];
        if (file_put_contents($targetPath, $content) === false) {
            throw new RuntimeException('Smoke archive не смог восстановить объект.');
        }
    }

    public function metadata(string $objectKey): ?array
    {
        $object = $this->objects[$objectKey] ?? null;
        if ($object === null) {
            return null;
        }
        return [
            'bytes' => $object['bytes'],
            'sha256' => $object['sha256'],
        ];
    }

    public function delete(string $objectKey): void
    {
        unset($this->objects[$objectKey]);
    }
}

$pdo = DatabaseManager::getInstance()->connection();
$tables = $pdo->query(
    "SELECT COUNT(*) FROM media_archive_locations"
)->fetchColumn();
if ($tables === false) {
    fwrite(STDERR, "Таблица media_archive_locations недоступна.\n");
    exit(1);
}

$root = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'churchcms-media-storage-smoke-'
    . bin2hex(random_bytes(6));
$applicationRoot = dirname(__DIR__);
$storage = new MediaBlobStorage(
    root: $root,
    maxBytes: 16 * 1024 * 1024,
    applicationRoot: $applicationRoot,
);
$repository = new MediaArchiveRepository($pdo);
$backend = new MediaArchiveSmokeBackend();
$archive = new MediaArchiveService($storage, $repository, $backend);

$source = tempnam(sys_get_temp_dir(), 'churchcms-media-source-');
if (!is_string($source)) {
    fwrite(STDERR, "Не удалось создать исходный smoke-файл.\n");
    exit(1);
}

try {
    $payload = "%PDF-1.4\nChurchCMS Media archive smoke\n%%EOF\n";
    file_put_contents($source, $payload);
    $blob = $storage->storeFile($source);
    $localPath = $storage->readablePath($blob->sha256, $blob->bytes);
    if ($localPath === null) {
        throw new RuntimeException('Исходный Media blob не сохранён локально.');
    }

    $archive->archive(
        sha256: $blob->sha256,
        bytes: $blob->bytes,
        mimeType: $blob->mimeType,
        removeLocal: true,
    );

    if ($storage->readablePath($blob->sha256, $blob->bytes) !== null) {
        throw new RuntimeException('После проверенного архивирования локальная копия не удалена.');
    }

    $location = $repository->find($blob->sha256);
    if (
        $location === null
        || ($location['state'] ?? null) !== 'ready'
        || ($location['backend'] ?? null) !== 'smoke'
    ) {
        throw new RuntimeException('Архивная копия не зафиксирована как готовая.');
    }

    $restored = $archive->ensureLocal($blob->sha256, $blob->bytes);
    if (
        $restored === null
        || hash_file('sha256', $restored) !== $blob->sha256
        || file_get_contents($restored) !== $payload
    ) {
        throw new RuntimeException('Архивный Media blob не восстановлен без потери данных.');
    }

    @unlink($restored);
    $backend->corruptFetch = true;
    try {
        $archive->ensureLocal($blob->sha256, $blob->bytes);
        throw new RuntimeException('Повреждённый архив ошибочно прошёл восстановление.');
    } catch (RuntimeException $error) {
        if (str_contains($error->getMessage(), 'ошибочно прошёл')) {
            throw $error;
        }
    }

    if ($storage->readablePath($blob->sha256, $blob->bytes) !== null) {
        throw new RuntimeException('Повреждённый архив оставил локальный blob после неудачной проверки.');
    }

    $backend->corruptFetch = false;
    $restoredAgain = $archive->ensureLocal($blob->sha256, $blob->bytes);
    if ($restoredAgain === null) {
        throw new RuntimeException('Повторное корректное восстановление Media не сработало.');
    }

    echo "Media storage archive/restore smoke OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Media storage smoke: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    @unlink($source);
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($root);
    }
}
