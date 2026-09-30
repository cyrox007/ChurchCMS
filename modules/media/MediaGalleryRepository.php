<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class MediaGalleryRepository
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

    public function findByPublicId(
        string $publicId,
        string $siteKey = 'default',
    ): ?MediaGallery {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_galleries
             WHERE public_id = :public_id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => trim($publicId),
            'site_key' => $siteKey,
        ]);
        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrate($row)
            : null;
    }

    /**
     * @return list<MediaGallery>
     */
    public function publicPublished(
        string $siteKey = 'default',
        int $limit = 50,
    ): array {
        $limit = max(1, min(100, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_galleries
             WHERE site_key = :site_key
               AND status = :status
               AND visibility = :visibility
             ORDER BY updated_at DESC, public_id DESC
             LIMIT ' . $limit
        );
        $statement->execute([
            'site_key' => $siteKey,
            'status' => 'published',
            'visibility' => 'public',
        ]);

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

    private static function hydrate(array $row): MediaGallery
    {
        return new MediaGallery(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            ownerOrganizationPublicId:
                (string) $row['owner_organization_public_id'],
            status: (string) $row['status'],
            visibility: (string) $row['visibility'],
            title: (string) $row['title'],
            description: (string) $row['description'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
