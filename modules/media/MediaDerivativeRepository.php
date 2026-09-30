<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class MediaDerivativeRepository
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
        string $variant,
        string $siteKey = 'default',
    ): ?MediaDerivative {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_derivatives
             WHERE site_key = :site_key
               AND media_public_id = :media_public_id
               AND variant = :variant
             LIMIT 1'
        );
        $statement->execute([
            'site_key' => $siteKey,
            'media_public_id' => $mediaPublicId,
            'variant' => $variant,
        ]);
        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrate($row)
            : null;
    }

    /**
     * @return list<MediaDerivative>
     */
    public function forAsset(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_derivatives
             WHERE site_key = :site_key
               AND media_public_id = :media_public_id
             ORDER BY variant'
        );
        $statement->execute([
            'site_key' => $siteKey,
            'media_public_id' => $mediaPublicId,
        ]);

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

    public function save(
        string $mediaPublicId,
        string $variant,
        MediaStoredBlob $blob,
        int $width,
        int $height,
        string $siteKey = 'default',
    ): MediaDerivative {
        $existing = $this->find(
            $mediaPublicId,
            $variant,
            $siteKey,
        );
        $now = gmdate('Y-m-d H:i:s');

        if ($existing === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO media_derivatives (
                    site_key,
                    media_public_id,
                    variant,
                    sha256,
                    mime_type,
                    bytes,
                    pixel_width,
                    pixel_height,
                    created_at,
                    updated_at
                 ) VALUES (
                    :site_key,
                    :media_public_id,
                    :variant,
                    :sha256,
                    :mime_type,
                    :bytes,
                    :pixel_width,
                    :pixel_height,
                    :created_at,
                    :updated_at
                 )'
            );
            $statement->execute([
                'site_key' => $siteKey,
                'media_public_id' => $mediaPublicId,
                'variant' => $variant,
                'sha256' => $blob->sha256,
                'mime_type' => $blob->mimeType,
                'bytes' => $blob->bytes,
                'pixel_width' => $width,
                'pixel_height' => $height,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE media_derivatives
                 SET sha256 = :sha256,
                     mime_type = :mime_type,
                     bytes = :bytes,
                     pixel_width = :pixel_width,
                     pixel_height = :pixel_height,
                     updated_at = :updated_at
                 WHERE site_key = :site_key
                   AND media_public_id = :media_public_id
                   AND variant = :variant'
            );
            $statement->execute([
                'sha256' => $blob->sha256,
                'mime_type' => $blob->mimeType,
                'bytes' => $blob->bytes,
                'pixel_width' => $width,
                'pixel_height' => $height,
                'updated_at' => $now,
                'site_key' => $siteKey,
                'media_public_id' => $mediaPublicId,
                'variant' => $variant,
            ]);
        }

        $saved = $this->find(
            $mediaPublicId,
            $variant,
            $siteKey,
        );

        if ($saved === null) {
            throw new \RuntimeException(
                'Производный Media-файл не удалось перечитать.'
            );
        }

        return $saved;
    }

    private static function hydrate(array $row): MediaDerivative
    {
        return new MediaDerivative(
            id: (int) $row['id'],
            siteKey: (string) $row['site_key'],
            mediaPublicId: (string) $row['media_public_id'],
            variant: (string) $row['variant'],
            sha256: (string) $row['sha256'],
            mimeType: (string) $row['mime_type'],
            bytes: (int) $row['bytes'],
            pixelWidth: (int) $row['pixel_width'],
            pixelHeight: (int) $row['pixel_height'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
