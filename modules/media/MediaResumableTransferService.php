<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\SecretVault;
use ChurchCMS\Core\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

final class MediaResumableTransferService
{
    public function __construct(
        private readonly MediaResumableTransferRepository $transfers,
        private readonly MediaBinarySourceService $binary,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            MediaResumableTransferRepository::fromDatabase(),
            MediaBinarySourceService::fromConfig(),
        );
    }

    public function source(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): MediaBinarySource {
        return $this->binary->resolveVideo(
            $mediaPublicId,
            $siteKey,
        );
    }

    public function begin(
        MediaBinarySource $source,
        string $providerId,
        string $targetKey,
        string $session,
        ?DateTimeImmutable $expiresAt = null,
    ): MediaResumableTransfer {
        $providerId = self::providerId($providerId);
        $targetKey = self::targetKey($targetKey);
        $session = trim($session);

        if ($session === '') {
            throw new InvalidArgumentException(
                'Удалённая resumable-сессия не может быть пустой.'
            );
        }

        $existing = $this->transfers->find(
            $source->mediaPublicId,
            $providerId,
            $targetKey,
            $source->siteKey,
        );

        $now = gmdate('Y-m-d H:i:s');
        $expires = $expiresAt?->setTimezone(
            new DateTimeZone('UTC'),
        )->format('Y-m-d H:i:s');

        if ($existing === null) {
            $this->transfers->insert([
                'public_id' => Uuid::v4(),
                'site_key' => $source->siteKey,
                'media_public_id' => $source->mediaPublicId,
                'provider_id' => $providerId,
                'target_key' => $targetKey,
                'session_encrypted' => SecretVault::encrypt(
                    $session,
                ),
                'uploaded_bytes' => 0,
                'total_bytes' => $source->bytes,
                'status' => 'active',
                'last_error' => null,
                'expires_at' => $expires,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            if ($existing->status === 'completed') {
                throw new InvalidArgumentException(
                    'Эта resumable-передача уже завершена.'
                );
            }

            $this->transfers->update(
                $existing->id,
                [
                    'session_encrypted' => SecretVault::encrypt(
                        $session,
                    ),
                    'uploaded_bytes' => 0,
                    'total_bytes' => $source->bytes,
                    'status' => 'active',
                    'last_error' => null,
                    'expires_at' => $expires,
                    'updated_at' => $now,
                ],
            );
        }

        $transfer = $this->transfers->find(
            $source->mediaPublicId,
            $providerId,
            $targetKey,
            $source->siteKey,
        );

        if ($transfer === null) {
            throw new RuntimeException(
                'Resumable transfer не удалось перечитать.'
            );
        }

        return $transfer;
    }

    public function find(
        string $mediaPublicId,
        string $providerId,
        string $targetKey,
        string $siteKey = 'default',
    ): ?MediaResumableTransfer {
        return $this->transfers->find(
            trim($mediaPublicId),
            self::providerId($providerId),
            self::targetKey($targetKey),
            $siteKey,
        );
    }

    public function session(
        MediaResumableTransfer $transfer,
    ): string {
        if (
            $transfer->expiresAt !== null
            && new DateTimeImmutable(
                $transfer->expiresAt,
                new DateTimeZone('UTC'),
            ) <= new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            )
        ) {
            throw new InvalidArgumentException(
                'Resumable-сессия истекла.'
            );
        }

        return SecretVault::decrypt(
            $transfer->sessionEncrypted,
        );
    }

    public function readNext(
        MediaResumableTransfer $transfer,
        int $maxBytes,
    ): MediaBinaryChunk {
        $current = $this->requiredByPublicId(
            $transfer->publicId,
        );

        if ($current->status !== 'active') {
            throw new InvalidArgumentException(
                'Resumable transfer не находится в активном состоянии.'
            );
        }

        $source = $this->source(
            $current->mediaPublicId,
            $current->siteKey,
        );

        if ($source->bytes !== $current->totalBytes) {
            throw new RuntimeException(
                'Размер Media source изменился после начала transfer.'
            );
        }

        return $this->binary->readChunk(
            $source,
            $current->uploadedBytes,
            $maxBytes,
        );
    }

    public function advance(
        MediaResumableTransfer $transfer,
        MediaBinaryChunk $chunk,
    ): MediaResumableTransfer {
        $current = $this->requiredByPublicId(
            $transfer->publicId,
        );

        if (
            $current->status !== 'active'
            || $chunk->offset !== $current->uploadedBytes
            || $chunk->totalBytes !== $current->totalBytes
            || $chunk->nextOffset <= $chunk->offset
        ) {
            throw new InvalidArgumentException(
                'Media chunk не соответствует текущему resumable transfer.'
            );
        }

        if (
            !$this->transfers->advance(
                $current->id,
                $current->uploadedBytes,
                $chunk->nextOffset,
            )
        ) {
            throw new RuntimeException(
                'Resumable transfer уже изменён другим worker.'
            );
        }

        return $this->requiredByPublicId(
            $current->publicId,
        );
    }

    public function complete(
        MediaResumableTransfer $transfer,
    ): MediaResumableTransfer {
        $current = $this->requiredByPublicId(
            $transfer->publicId,
        );

        if (
            $current->status !== 'active'
            || $current->uploadedBytes !== $current->totalBytes
        ) {
            throw new InvalidArgumentException(
                'Нельзя завершить transfer до отправки всех байтов.'
            );
        }

        if (
            !$this->transfers->complete(
                $current->id,
                $current->uploadedBytes,
                $current->totalBytes,
            )
        ) {
            throw new RuntimeException(
                'Resumable transfer уже изменён другим worker.'
            );
        }

        return $this->requiredByPublicId(
            $current->publicId,
        );
    }

    public function fail(
        MediaResumableTransfer $transfer,
        string $error,
    ): MediaResumableTransfer {
        $current = $this->requiredByPublicId(
            $transfer->publicId,
        );
        $error = self::safeError($error);

        $this->transfers->update(
            $current->id,
            [
                'status' => 'failed',
                'last_error' => $error,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
        );

        return $this->requiredByPublicId(
            $current->publicId,
        );
    }

    private function requiredByPublicId(
        string $publicId,
    ): MediaResumableTransfer {
        $transfer = $this->transfers->findByPublicId(
            $publicId,
        );

        if ($transfer === null) {
            throw new RuntimeException(
                'Resumable transfer не найден после обновления.'
            );
        }

        return $transfer;
    }

    private static function providerId(string $value): string
    {
        $value = strtolower(trim($value));

        if (
            preg_match(
                '/^[a-z][a-z0-9_.-]{1,63}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный provider ID resumable transfer.'
            );
        }

        return $value;
    }

    private static function targetKey(string $value): string
    {
        $value = trim($value);

        if (
            preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный target key resumable transfer.'
            );
        }

        return $value;
    }

    private static function safeError(string $value): string
    {
        $value = str_replace(
            ["\r", "\n", "\0"],
            [' ', ' ', ''],
            trim($value),
        );

        return substr(
            $value !== '' ? $value : 'Неизвестная ошибка transfer.',
            0,
            1000,
        );
    }
}
