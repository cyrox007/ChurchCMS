<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\DatabaseManager;
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
