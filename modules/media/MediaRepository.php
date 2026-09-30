<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
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

    public function findByPublicIdForUpdate(
        string $publicId,
        string $siteKey = 'default',
    ): ?MediaAsset {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_assets
             WHERE public_id = :public_id
               AND site_key = :site_key
             LIMIT 1
             FOR UPDATE'
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
     * null означает глобальный доступ без owner-фильтра.
     *
     * @param list<string>|null $ownerPublicIds
     * @return list<MediaAsset>
     */
    public function adminList(
        ?array $ownerPublicIds,
        string $siteKey = 'default',
        int $limit = 100,
    ): array {
        $limit = max(1, min(500, $limit));

        if ($ownerPublicIds === []) {
            return [];
        }

        $where = [
            'site_key = :site_key',
        ];
        $params = [
            'site_key' => $siteKey,
        ];

        if ($ownerPublicIds !== null) {
            $placeholders = [];

            foreach (
                array_values(array_unique($ownerPublicIds))
                as $index => $publicId
            ) {
                $name = 'owner_' . $index;
                $placeholders[] = ':' . $name;
                $params[$name] = $publicId;
            }

            $where[] = 'owner_organization_public_id IN ('
                . implode(', ', $placeholders)
                . ')';
        }

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_assets
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $statement->execute($params);

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

    /**
     * @return list<MediaAsset>
     */
    public function publicVisible(
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
             ORDER BY updated_at DESC, public_id ASC
             LIMIT ' . $limit
        );
        $statement->execute([
            'site_key' => $siteKey,
            'visibility' => 'public',
            'archived' => 'archived',
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


    /**
     * @return list<MediaAsset>
     */
    public function federatedUpdatedSince(
        DateTimeImmutable $updatedSince,
        string $siteKey = 'default',
        int $limit = 100,
        ?string $afterPublicId = null,
    ): array {
        $limit = max(1, min(100, $limit));
        $timestamp = $updatedSince
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        if (
            $afterPublicId !== null
            && preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $afterPublicId,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный public ID курсора медиатеки.'
            );
        }

        $cursorSql = $afterPublicId === null
            ? 'updated_at > :updated_since'
            : '(updated_at > :updated_since
                OR (
                    updated_at = :same_updated_at
                    AND public_id > :after_public_id
                ))';

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_assets
             WHERE site_key = :site_key
               AND visibility = :visibility
               AND status <> :archived
               AND ' . $cursorSql . '
             ORDER BY updated_at ASC, public_id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':site_key', $siteKey);
        $statement->bindValue(':visibility', 'federated');
        $statement->bindValue(':archived', 'archived');
        $statement->bindValue(':updated_since', $timestamp);
        if ($afterPublicId !== null) {
            $statement->bindValue(':same_updated_at', $timestamp);
            $statement->bindValue(
                ':after_public_id',
                $afterPublicId,
            );
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

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
            pixelWidth: self::nullableInt(
                $row['pixel_width'] ?? null,
            ),
            pixelHeight: self::nullableInt(
                $row['pixel_height'] ?? null,
            ),
            title: self::nullable($row['title'] ?? null),
            altText: self::nullable($row['alt_text'] ?? null),
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && $value !== ''
            ? $value
            : null;
    }
}
