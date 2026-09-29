<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

final class DocumentRepository
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
    ): ?DocumentRecord {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM documents
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
     * @return list<DocumentRecord>
     */
    public function forOrganization(
        string $organizationPublicId,
        string $siteKey = 'default',
        int $limit = 100,
    ): array {
        $limit = max(1, min(500, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM documents
             WHERE owner_organization_public_id = :organization_id
               AND site_key = :site_key
             ORDER BY
                 issued_on DESC,
                 created_at DESC,
                 id DESC
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
     * Возвращает только локально опубликованные публичные документы.
     *
     * @return list<DocumentRecord>
     */
    public function publishedPublic(
        string $siteKey = 'default',
        int $limit = 50,
    ): array {
        $limit = max(1, min(50, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM documents
             WHERE site_key = :site_key
               AND status = :status
               AND visibility = :visibility
             ORDER BY
                 CASE WHEN issued_on IS NULL THEN 1 ELSE 0 END ASC,
                 issued_on DESC,
                 updated_at DESC,
                 public_id DESC
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

    /**
     * @return list<DocumentRecord>
     */
    public function forFederation(
        string $siteKey = 'default',
        int $limit = 100,
    ): array {
        $limit = max(1, min(500, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM documents
             WHERE site_key = :site_key
               AND visibility = :visibility
               AND status = :status
             ORDER BY updated_at ASC, public_id ASC
             LIMIT ' . $limit
        );
        $statement->execute([
            'site_key' => $siteKey,
            'visibility' => 'federated',
            'status' => 'published',
        ]);

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }


    /**
     * @return list<DocumentRecord>
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
                'Некорректный public ID курсора документов.'
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
             FROM documents
             WHERE site_key = :site_key
               AND visibility = :visibility
               AND status = :status
               AND ' . $cursorSql . '
             ORDER BY updated_at ASC, public_id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':site_key', $siteKey);
        $statement->bindValue(':visibility', 'federated');
        $statement->bindValue(':status', 'published');
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

    private static function hydrate(array $row): DocumentRecord
    {
        return new DocumentRecord(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            ownerOrganizationPublicId:
                (string) $row['owner_organization_public_id'],
            status: (string) $row['status'],
            visibility: (string) ($row['visibility'] ?? 'private'),
            title: (string) $row['title'],
            documentType: (string) $row['document_type'],
            documentNumber: self::nullable(
                $row['document_number'] ?? null,
            ),
            issuedOn: self::nullable($row['issued_on'] ?? null),
            summary: (string) $row['summary'],
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
