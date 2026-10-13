<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

interface MediaArchiveBackend
{
    public function id(): string;

    /**
     * Сохраняет blob под стабильным ключом. Повторная запись того же ключа
     * должна быть безопасной и идемпотентной.
     */
    public function put(
        string $sourcePath,
        string $objectKey,
        string $sha256,
        int $bytes,
        string $mimeType,
    ): void;

    /**
     * Загружает объект во временный локальный файл.
     */
    public function fetch(
        string $objectKey,
        string $targetPath,
    ): void;

    /**
     * @return array{bytes:int,sha256:?string}|null
     */
    public function metadata(string $objectKey): ?array;

    public function delete(string $objectKey): void;
}
