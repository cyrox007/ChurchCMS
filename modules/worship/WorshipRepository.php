<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

final class WorshipRepository
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
    ): ?WorshipService {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM worship_services
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
     * @return list<WorshipService>
     */
    public function forOrganization(
        string $organizationPublicId,
        string $siteKey = 'default',
        int $limit = 100,
    ): array {
        $limit = max(1, min(500, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM worship_services
             WHERE owner_organization_public_id = :organization_id
               AND site_key = :site_key
             ORDER BY starts_at ASC, id ASC
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
     * @return list<WorshipService>
     */
    public function visibleUpdatedSince(
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
                'Некорректный public ID курсора богослужений.'
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
             FROM worship_services
             WHERE site_key = :site_key
               AND status IN (\'scheduled\', \'cancelled\')
               AND ' . $cursorSql . '
             ORDER BY updated_at ASC, public_id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':site_key', $siteKey);
        $statement->bindValue(':updated_since', $timestamp);
        if ($afterPublicId !== null) {
            $statement->bindValue(
                ':same_updated_at',
                $timestamp,
            );
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


    /**
     * @return list<WorshipService>
     */
    public function visibleUpcoming(
        string $siteKey = 'default',
        int $limit = 100,
        ?DateTimeImmutable $from = null,
    ): array {
        $limit = max(1, min(200, $limit));
        $from ??= new DateTimeImmutable(
            'now',
            new DateTimeZone('UTC'),
        );
        $from = $from->setTimezone(
            new DateTimeZone('UTC'),
        );

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM worship_services
             WHERE site_key = :site_key
               AND status IN (\'scheduled\', \'cancelled\')
               AND starts_at >= :starts_at
             ORDER BY starts_at ASC, public_id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':site_key', $siteKey);
        $statement->bindValue(
            ':starts_at',
            $from->format('Y-m-d H:i:s'),
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

private static function hydrate(array $row): WorshipService
    {
        return new WorshipService(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            ownerOrganizationPublicId:
                (string) $row['owner_organization_public_id'],
            status: (string) $row['status'],
            title: (string) $row['title'],
            serviceType: (string) $row['service_type'],
            startsAt: (string) $row['starts_at'],
            endsAt: self::nullable($row['ends_at'] ?? null),
            locationName: self::nullable(
                $row['location_name'] ?? null,
            ),
            descriptionHtml: (string) $row['description_html'],
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
