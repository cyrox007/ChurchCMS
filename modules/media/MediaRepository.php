<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class MediaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function findByPublicId(
        string $publicId,
        string $siteKey = 'default',
    ): ?MediaAsset {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_assets
             WHERE public_id = :public_id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrate($row)
            : null;
    }

    /**
     * @return list<MediaAsset>
     */
    public function forOrganization(
        string $organizationPublicId,
        string $siteKey = 'default',
        int $limit = 100,
    ): array {
        $limit = max(1, min(500, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_assets
             WHERE owner_organization_public_id = :organization_id
               AND site_key = :site_key
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $statement->execute([
            'organization_id' => $organizationPublicId,
            'site_key' => $siteKey,
        ]);

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

    /**
     * @return list<MediaAsset>
     */
    public function forFederation(
        string $siteKey = 'default',
        int $limit = 100,
    ): array {
        $limit = max(1, min(500, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_assets
             WHERE site_key = :site_key
               AND visibility = :visibility
               AND status <> :archived
             ORDER BY updated_at ASC, public_id ASC
             LIMIT ' . $limit
        );
        $statement->execute([
            'site_key' => $siteKey,
            'visibility' => 'federated',
            'archived' => 'archived',
        ]);

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

    private static function hydrate(array $row): MediaAsset
    {
        return new MediaAsset(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            ownerOrganizationPublicId:
                (string) $row['owner_organization_public_id'],
            status: (string) $row['status'],
            visibility: (string) ($row['visibility'] ?? 'private'),
            mediaType: (string) $row['media_type'],
            originalName: (string) $row['original_name'],
            mimeType: (string) $row['mime_type'],
            bytes: (int) $row['bytes'],
            sha256: (string) $row['sha256'],
            title: self::nullable($row['title'] ?? null),
            altText: self::nullable($row['alt_text'] ?? null),
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && $value !== ''
            ? $value
            : null;
    }
}
