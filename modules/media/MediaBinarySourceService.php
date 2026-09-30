<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use InvalidArgumentException;
use RuntimeException;

final class MediaBinarySourceService
{
    private const MAX_CHUNK_BYTES = 16777216;

    public function __construct(
        private readonly MediaBlobStorage $storage,
        private readonly MediaRepository $media,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            MediaBlobStorage::fromConfig(),
            MediaRepository::fromDatabase(),
        );
    }

    public function resolveVideo(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): MediaBinarySource {
        $asset = $this->media->findByPublicId(
            trim($mediaPublicId),
            $siteKey,
        );

        if (
            $asset === null
            || $asset->status === 'archived'
            || $asset->mediaType !== 'video'
        ) {
            throw new InvalidArgumentException(
                'Видео Media недоступно для бинарной отправки.'
            );
        }

        $path = $this->storage->readablePath(
            $asset->sha256,
            $asset->bytes,
        );

        if ($path === null) {
            throw new RuntimeException(
                'Media blob видео отсутствует или повреждён.'
            );
        }

        $actualHash = hash_file(
            'sha256',
            $path,
        );

        if (
            !is_string($actualHash)
            || !hash_equals(
                $asset->sha256,
                $actualHash,
            )
        ) {
            throw new RuntimeException(
                'SHA-256 видео Media не совпадает с реестром.'
            );
        }

        return new MediaBinarySource(
            mediaPublicId: $asset->publicId,
            siteKey: $asset->siteKey,
            path: $path,
            mimeType: $asset->mimeType,
            bytes: $asset->bytes,
            sha256: $asset->sha256,
        );
    }

    public function readChunk(
        MediaBinarySource $source,
        int $offset,
        int $maxBytes,
    ): MediaBinaryChunk {
        if (
            $offset < 0
            || $offset > $source->bytes
        ) {
            throw new InvalidArgumentException(
                'Некорректное смещение Media chunk.'
            );
        }

        if (
            $maxBytes <= 0
            || $maxBytes > self::MAX_CHUNK_BYTES
        ) {
            throw new InvalidArgumentException(
                'Размер Media chunk должен быть от 1 байта до 16 МиБ.'
            );
        }

        if ($offset === $source->bytes) {
            return new MediaBinaryChunk(
                offset: $offset,
                data: '',
                nextOffset: $offset,
                totalBytes: $source->bytes,
            );
        }

        $handle = fopen(
            $source->path,
            'rb',
        );

        if ($handle === false) {
            throw new RuntimeException(
                'Не удалось открыть Media blob для чтения chunk.'
            );
        }

        try {
            if (fseek($handle, $offset, SEEK_SET) !== 0) {
                throw new RuntimeException(
                    'Не удалось перейти к смещению Media chunk.'
                );
            }

            $remaining = $source->bytes - $offset;
            $length = min(
                $remaining,
                $maxBytes,
            );
            $data = '';

            while (strlen($data) < $length) {
                $chunk = fread(
                    $handle,
                    $length - strlen($data),
                );

                if ($chunk === false) {
                    throw new RuntimeException(
                        'Ошибка чтения Media chunk.'
                    );
                }

                if ($chunk === '') {
                    break;
                }

                $data .= $chunk;
            }

            if (strlen($data) !== $length) {
                throw new RuntimeException(
                    'Media blob закончился раньше зарегистрированного размера.'
                );
            }
        } finally {
            fclose($handle);
        }

        return new MediaBinaryChunk(
            offset: $offset,
            data: $data,
            nextOffset: $offset + strlen($data),
            totalBytes: $source->bytes,
        );
    }
}
