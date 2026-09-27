<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\DatabaseManager;
use PDO;
use RuntimeException;

final class OrganizationRepository
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
    ): ?OrganizationUnit {
        $statement = $this->pdo->prepare(
            'SELECT * FROM organization_units
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
            ? $this->hydrate($row)
            : null;
    }

    public function findById(
        int $id,
        string $siteKey = 'default',
    ): ?OrganizationUnit {
        if ($id <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM organization_units
             WHERE id = :id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'site_key' => $siteKey,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $this->hydrate($row)
            : null;
    }

    public function findByPath(
        string $path,
        string $siteKey = 'default',
    ): ?OrganizationUnit {
        $statement = $this->pdo->prepare(
            'SELECT * FROM organization_units
             WHERE path = :path
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'path' => $path,
            'site_key' => $siteKey,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $this->hydrate($row)
            : null;
    }

    public function siteRoot(
        string $siteKey = 'default',
    ): ?OrganizationUnit {
        $statement = $this->pdo->prepare(
            'SELECT u.*
             FROM organization_site_roots r
             INNER JOIN organization_units u
                ON u.id = r.organization_id
             WHERE r.site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'site_key' => $siteKey,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $this->hydrate($row)
            : null;
    }

    /**
     * @return list<OrganizationUnit>
     */
    public function tree(
        string $siteKey = 'default',
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT * FROM organization_units
             WHERE site_key = :site_key
             ORDER BY path ASC, sort_order ASC, id ASC'
        );
        $statement->execute([
            'site_key' => $siteKey,
        ]);

        return array_map(
            fn(array $row): OrganizationUnit =>
                $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    private function hydrate(array $row): OrganizationUnit
    {
        $type = trim((string) ($row['unit_type'] ?? ''));
        $status = trim((string) ($row['status'] ?? ''));

        if ($type === '' || $status === '') {
            throw new RuntimeException(
                'Организационная единица содержит некорректные данные.'
            );
        }

        return new OrganizationUnit(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            parentId: isset($row['parent_id'])
                ? (int) $row['parent_id']
                : null,
            type: $type,
            status: $status,
            slug: (string) $row['slug'],
            path: (string) $row['path'],
            name: (string) $row['name'],
            shortName: isset($row['short_name'])
                && $row['short_name'] !== ''
                ? (string) $row['short_name']
                : null,
            legalName: isset($row['legal_name'])
                && $row['legal_name'] !== ''
                ? (string) $row['legal_name']
                : null,
            descriptionHtml: (string) (
                $row['description_html'] ?? ''
            ),
            sortOrder: (int) ($row['sort_order'] ?? 0),
        );
    }
}
