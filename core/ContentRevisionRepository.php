<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use InvalidArgumentException;
use JsonException;
use PDO;
use RuntimeException;

final class ContentRevisionRepository
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

    /**
     * @param array<string,mixed> $snapshot
     */
    public function append(
        string $entityType,
        string $entityPublicId,
        array $snapshot,
        string $siteKey = 'default',
    ): ContentRevision {
        $entityType = self::entityType($entityType);
        $entityPublicId = self::uuid($entityPublicId);
        $siteKey = self::siteKey($siteKey);

        try {
            $snapshotJson = json_encode(
                $snapshot,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $error) {
            throw new InvalidArgumentException(
                'Снимок revision не удалось сериализовать.',
                0,
                $error,
            );
        }

        if (strlen($snapshotJson) > 1048576) {
            throw new InvalidArgumentException(
                'Снимок revision превышает 1 МиБ.'
            );
        }

        $numberStatement = $this->pdo->prepare(
            'SELECT COALESCE(MAX(revision_number), 0)
             FROM content_revisions
             WHERE site_key = :site_key
               AND entity_type = :entity_type
               AND entity_public_id = :entity_public_id'
        );
        $numberStatement->execute([
            'site_key' => $siteKey,
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
        ]);
        $revisionNumber =
            (int) $numberStatement->fetchColumn() + 1;

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO content_revisions (
                public_id,
                site_key,
                entity_type,
                entity_public_id,
                revision_number,
                snapshot_json,
                created_at
             ) VALUES (
                :public_id,
                :site_key,
                :entity_type,
                :entity_public_id,
                :revision_number,
                :snapshot_json,
                :created_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'revision_number' => $revisionNumber,
            'snapshot_json' => $snapshotJson,
            'created_at' => $now,
        ]);

        $revision = $this->findByPublicId(
            $publicId,
            $siteKey,
        );

        if ($revision === null) {
            throw new RuntimeException(
                'Созданный revision не удалось перечитать.'
            );
        }

        return $revision;
    }

    /**
     * @return list<ContentRevision>
     */
    public function forEntity(
        string $entityType,
        string $entityPublicId,
        string $siteKey = 'default',
        int $limit = 50,
    ): array {
        $entityType = self::entityType($entityType);
        $entityPublicId = self::uuid($entityPublicId);
        $siteKey = self::siteKey($siteKey);
        $limit = max(1, min(200, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM content_revisions
             WHERE site_key = :site_key
               AND entity_type = :entity_type
               AND entity_public_id = :entity_public_id
             ORDER BY revision_number DESC
             LIMIT :limit'
        );
        $statement->bindValue(':site_key', $siteKey);
        $statement->bindValue(':entity_type', $entityType);
        $statement->bindValue(
            ':entity_public_id',
            $entityPublicId,
        );
        $statement->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT,
        );
        $statement->execute();

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

    public function findByPublicId(
        string $publicId,
        string $siteKey = 'default',
    ): ?ContentRevision {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM content_revisions
             WHERE public_id = :public_id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => self::uuid($publicId),
            'site_key' => self::siteKey($siteKey),
        ]);
        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrate($row)
            : null;
    }

    private static function hydrate(
        array $row,
    ): ContentRevision {
        try {
            $snapshot = json_decode(
                (string) $row['snapshot_json'],
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $error) {
            throw new RuntimeException(
                'Revision содержит повреждённый JSON.',
                0,
                $error,
            );
        }

        if (!is_array($snapshot)) {
            throw new RuntimeException(
                'Revision содержит некорректный снимок.'
            );
        }

        return new ContentRevision(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            entityType: (string) $row['entity_type'],
            entityPublicId:
                (string) $row['entity_public_id'],
            revisionNumber:
                (int) $row['revision_number'],
            snapshot: $snapshot,
            createdAt: (string) $row['created_at'],
        );
    }

    private static function entityType(
        string $value,
    ): string {
        $value = trim($value);

        if (
            preg_match(
                '/^[a-z][a-z0-9_.-]{1,31}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный тип revision.'
            );
        }

        return $value;
    }

    private static function uuid(string $value): string
    {
        $value = trim($value);

        if (
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный public ID revision.'
            );
        }

        return strtolower($value);
    }

    private static function siteKey(string $value): string
    {
        $value = trim($value);

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9_.-]{0,63}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный site key revision.'
            );
        }

        return $value;
    }
}
