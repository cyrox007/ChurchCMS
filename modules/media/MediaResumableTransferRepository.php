<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class MediaResumableTransferRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function find(
        string $mediaPublicId,
        string $providerId,
        string $targetKey,
        string $siteKey = 'default',
    ): ?MediaResumableTransfer {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_resumable_transfers
             WHERE site_key = :site_key
               AND media_public_id = :media_public_id
               AND provider_id = :provider_id
               AND target_key = :target_key
             LIMIT 1'
        );
        $statement->execute([
            'site_key' => $siteKey,
            'media_public_id' => $mediaPublicId,
            'provider_id' => $providerId,
            'target_key' => $targetKey,
        ]);
        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrate($row)
            : null;
    }

    public function findByPublicId(
        string $publicId,
    ): ?MediaResumableTransfer {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_resumable_transfers
             WHERE public_id = :public_id
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => trim($publicId),
        ]);
        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrate($row)
            : null;
    }

    /**
     * @param array<string,mixed> $fields
     */
    public function update(
        int $id,
        array $fields,
    ): void {
        if ($id <= 0 || $fields === []) {
            return;
        }

        $allowed = [
            'session_encrypted',
            'uploaded_bytes',
            'total_bytes',
            'status',
            'last_error',
            'expires_at',
            'updated_at',
        ];
        $sets = [];
        $params = ['id' => $id];

        foreach ($fields as $key => $value) {
            if (
                !is_string($key)
                || !in_array($key, $allowed, true)
            ) {
                continue;
            }

            $sets[] = $key . ' = :' . $key;
            $params[$key] = $value;
        }

        if ($sets === []) {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE media_resumable_transfers
             SET ' . implode(', ', $sets) . '
             WHERE id = :id'
        );
        $statement->execute($params);
    }

    private static function hydrate(
        array $row,
    ): MediaResumableTransfer {
        return new MediaResumableTransfer(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            mediaPublicId: (string) $row['media_public_id'],
            providerId: (string) $row['provider_id'],
            targetKey: (string) $row['target_key'],
            sessionEncrypted:
                (string) $row['session_encrypted'],
            uploadedBytes: (int) $row['uploaded_bytes'],
            totalBytes: (int) $row['total_bytes'],
            status: (string) $row['status'],
            lastError: isset($row['last_error'])
                ? (string) $row['last_error']
                : null,
            expiresAt: isset($row['expires_at'])
                ? (string) $row['expires_at']
                : null,
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
