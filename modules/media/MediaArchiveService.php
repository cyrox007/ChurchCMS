<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use RuntimeException;

final class MediaArchiveService
{
    public function __construct(
        private readonly MediaBlobStorage $local,
        private readonly MediaArchiveRepository $locations,
        private readonly ?MediaArchiveBackend $archive,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            MediaBlobStorage::fromConfig(),
            MediaArchiveRepository::fromDatabase(),
            MediaArchiveBackendFactory::fromConfig(),
        );
    }

    public function enabled(): bool
    {
        return $this->archive !== null;
    }

    /** @return array{enabled:bool,backend:?string} */
    public function status(): array
    {
        return [
            'enabled' => $this->archive !== null,
            'backend' => $this->archive?->id(),
        ];
    }

    /**
     * Архивирует локальный blob и обязательно перепроверяет его обратным
     * чтением перед тем, как разрешить удаление локальной копии.
     */
    public function archive(
        string $sha256,
        int $bytes,
        string $mimeType,
        bool $removeLocal = false,
    ): void {
        if ($this->archive === null) {
            throw new RuntimeException(
                'Внешний Media archive не настроен.'
            );
        }

        $path = $this->local->readablePath($sha256, $bytes);
        if ($path === null) {
            throw new RuntimeException(
                'Локальный Media blob недоступен для архивирования.'
            );
        }

        $objectKey = self::objectKey($sha256);
        $this->archive->put(
            $path,
            $objectKey,
            $sha256,
            $bytes,
            $mimeType,
        );

        $remote = $this->archive->metadata($objectKey);
        if (
            $remote === null
            || (int) $remote['bytes'] !== $bytes
            || !is_string($remote['sha256'])
            || !hash_equals($sha256, $remote['sha256'])
        ) {
            throw new RuntimeException(
                'Media archive не подтвердил размер и SHA-256 загруженного blob.'
            );
        }

        $temporary = tempnam(sys_get_temp_dir(), 'churchcms-archive-verify-');
        if (!is_string($temporary)) {
            throw new RuntimeException(
                'Не удалось создать временный файл проверки Media archive.'
            );
        }

        try {
            $this->archive->fetch($objectKey, $temporary);
            self::assertFile($temporary, $sha256, $bytes, 'Обратная проверка Media archive');
        } finally {
            @unlink($temporary);
        }

        $this->locations->markReady(
            $sha256,
            $this->archive->id(),
            $objectKey,
            $bytes,
        );

        if ($removeLocal) {
            $verifiedPath = $this->local->readablePath($sha256, $bytes);
            if ($verifiedPath === null || !@unlink($verifiedPath)) {
                throw new RuntimeException(
                    'Архив проверен, но локальный Media blob удалить не удалось.'
                );
            }
        }
    }

    /**
     * Возвращает локальный путь. Если blob был вынесен в архив, безопасно
     * восстанавливает его в локальный кэш с повторной проверкой SHA-256.
     */
    public function ensureLocal(
        string $sha256,
        int $bytes,
    ): ?string {
        $path = $this->local->readablePath($sha256, $bytes);
        if ($path !== null) {
            return $path;
        }

        if ($this->archive === null) {
            return null;
        }

        $location = $this->locations->find($sha256);
        if (
            $location === null
            || (string) ($location['state'] ?? '') !== 'ready'
            || (string) ($location['backend'] ?? '') !== $this->archive->id()
        ) {
            return null;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'churchcms-media-restore-');
        if (!is_string($temporary)) {
            throw new RuntimeException(
                'Не удалось создать временный файл восстановления Media.'
            );
        }

        try {
            $this->archive->fetch((string) $location['object_key'], $temporary);
            self::assertFile($temporary, $sha256, $bytes, 'Восстановленный Media blob');

            $stored = $this->local->storeFile($temporary);
            if (
                $stored->bytes !== $bytes
                || !hash_equals($sha256, $stored->sha256)
            ) {
                throw new RuntimeException(
                    'Локальный Media cache не подтвердил восстановленный blob.'
                );
            }
        } finally {
            @unlink($temporary);
        }

        $this->locations->markRestored($sha256);

        return $this->local->readablePath($sha256, $bytes);
    }

    private static function assertFile(
        string $path,
        string $sha256,
        int $bytes,
        string $label,
    ): void {
        $actualBytes = filesize($path);
        $actualHash = hash_file('sha256', $path);
        if (
            !is_int($actualBytes)
            || $actualBytes !== $bytes
            || !is_string($actualHash)
            || !hash_equals($sha256, $actualHash)
        ) {
            throw new RuntimeException(
                $label . ' не прошёл проверку размера и SHA-256.'
            );
        }
    }

    private static function objectKey(string $sha256): string
    {
        return 'sha256/'
            . substr($sha256, 0, 2)
            . '/'
            . substr($sha256, 2, 2)
            . '/'
            . $sha256
            . '.blob';
    }
}
