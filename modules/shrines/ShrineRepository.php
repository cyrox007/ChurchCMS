<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Shrines;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class ShrineRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /** @return list<ShrineRecord> */
    public function adminList(?array $ownerPublicIds = null, string $siteKey = 'default'): array
    {
        $sql = 'SELECT * FROM shrines WHERE site_key = :site_key';
        $params = ['site_key' => $siteKey];
        if ($ownerPublicIds !== null) {
            if ($ownerPublicIds === []) {
                return [];
            }
            $placeholders = [];
            foreach (array_values($ownerPublicIds) as $index => $ownerPublicId) {
                $key = 'owner_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $ownerPublicId;
            }
            $sql .= ' AND owner_organization_public_id IN (' . implode(', ', $placeholders) . ')';
        }
        $sql .= ' ORDER BY sort_order ASC, title ASC, id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    /** @return list<ShrineRecord> */
    public function published(string $siteKey = 'default'): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM shrines WHERE site_key = :site_key AND status = :status
             ORDER BY sort_order ASC, title ASC, id ASC'
        );
        $statement->execute(['site_key' => $siteKey, 'status' => 'published']);
        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    public function find(string $publicId, string $siteKey = 'default'): ?ShrineRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM shrines WHERE site_key = :site_key AND public_id = :public_id LIMIT 1'
        );
        $statement->execute(['site_key' => $siteKey, 'public_id' => $publicId]);
        $row = $statement->fetch();
        return is_array($row) ? self::hydrate($row) : null;
    }

    public function create(array $data): string
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO shrines (
                public_id, site_key, owner_organization_public_id, status, shrine_type,
                title, subtitle, location_name, summary, description_html, sort_order,
                created_at, updated_at
             ) VALUES (
                :public_id, :site_key, :owner_organization_public_id, :status, :shrine_type,
                :title, :subtitle, :location_name, :summary, :description_html, :sort_order,
                :created_at, :updated_at
             )'
        );
        $statement->execute($data);
        return (string) $data['public_id'];
    }

    public function update(string $publicId, array $data, string $siteKey = 'default'): void
    {
        $data['public_id'] = $publicId;
        $data['site_key'] = $siteKey;
        $statement = $this->pdo->prepare(
            'UPDATE shrines SET
                owner_organization_public_id = :owner_organization_public_id,
                shrine_type = :shrine_type,
                title = :title,
                subtitle = :subtitle,
                location_name = :location_name,
                summary = :summary,
                description_html = :description_html,
                sort_order = :sort_order,
                updated_at = :updated_at
             WHERE site_key = :site_key AND public_id = :public_id'
        );
        $statement->execute($data);
    }

    public function setStatus(string $publicId, string $status, string $siteKey = 'default'): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE shrines SET status = :status, updated_at = :updated_at
             WHERE site_key = :site_key AND public_id = :public_id'
        );
        $statement->execute([
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'site_key' => $siteKey,
            'public_id' => $publicId,
        ]);
    }

    private static function hydrate(array $row): ShrineRecord
    {
        return new ShrineRecord(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            ownerOrganizationPublicId: (string) $row['owner_organization_public_id'],
            status: (string) $row['status'],
            shrineType: (string) $row['shrine_type'],
            title: (string) $row['title'],
            subtitle: self::nullable($row['subtitle'] ?? null),
            locationName: self::nullable($row['location_name'] ?? null),
            summary: (string) ($row['summary'] ?? ''),
            descriptionHtml: (string) ($row['description_html'] ?? ''),
            sortOrder: (int) ($row['sort_order'] ?? 0),
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
