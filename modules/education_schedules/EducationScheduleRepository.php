<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationSchedules;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class EducationScheduleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /** @return list<EducationScheduleRecord> */
    public function adminList(?array $ownerPublicIds = null, string $siteKey = 'default'): array
    {
        $sql = 'SELECT s.*, p.title AS program_title,
                       p.owner_organization_public_id AS program_owner_organization_public_id
                FROM education_schedules s
                INNER JOIN education_programs p
                    ON p.site_key = s.site_key AND p.public_id = s.program_public_id
                WHERE s.site_key = :site_key';
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
            $sql .= ' AND p.owner_organization_public_id IN (' . implode(', ', $placeholders) . ')';
        }
        $sql .= ' ORDER BY s.starts_at_utc ASC, s.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    /** @return list<EducationScheduleRecord> */
    public function published(string $siteKey = 'default'): array
    {
        $statement = $this->pdo->prepare(
            "SELECT s.*, p.title AS program_title,
                    p.owner_organization_public_id AS program_owner_organization_public_id
             FROM education_schedules s
             INNER JOIN education_programs p
                ON p.site_key = s.site_key AND p.public_id = s.program_public_id
             WHERE s.site_key = :site_key
               AND s.status = 'published'
               AND p.status = 'published'
             ORDER BY s.starts_at_utc ASC, s.id ASC"
        );
        $statement->execute(['site_key' => $siteKey]);
        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    public function find(string $publicId, string $siteKey = 'default'): ?EducationScheduleRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT s.*, p.title AS program_title,
                    p.owner_organization_public_id AS program_owner_organization_public_id
             FROM education_schedules s
             INNER JOIN education_programs p
                ON p.site_key = s.site_key AND p.public_id = s.program_public_id
             WHERE s.site_key = :site_key AND s.public_id = :public_id
             LIMIT 1'
        );
        $statement->execute(['site_key' => $siteKey, 'public_id' => $publicId]);
        $row = $statement->fetch();
        return is_array($row) ? self::hydrate($row) : null;
    }

    public function create(array $data): string
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO education_schedules (
                public_id, site_key, program_public_id, status, title,
                starts_at_utc, ends_at_utc, timezone, location, note, created_at, updated_at
             ) VALUES (
                :public_id, :site_key, :program_public_id, :status, :title,
                :starts_at_utc, :ends_at_utc, :timezone, :location, :note, :created_at, :updated_at
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
            'UPDATE education_schedules SET
                program_public_id = :program_public_id,
                title = :title,
                starts_at_utc = :starts_at_utc,
                ends_at_utc = :ends_at_utc,
                timezone = :timezone,
                location = :location,
                note = :note,
                updated_at = :updated_at
             WHERE site_key = :site_key AND public_id = :public_id'
        );
        $statement->execute($data);
    }

    public function setStatus(string $publicId, string $status, string $siteKey = 'default'): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE education_schedules SET status = :status, updated_at = :updated_at WHERE site_key = :site_key AND public_id = :public_id'
        );
        $statement->execute([
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'site_key' => $siteKey,
            'public_id' => $publicId,
        ]);
    }

    private static function hydrate(array $row): EducationScheduleRecord
    {
        return new EducationScheduleRecord(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            programPublicId: (string) $row['program_public_id'],
            programTitle: (string) $row['program_title'],
            programOwnerOrganizationPublicId: (string) $row['program_owner_organization_public_id'],
            status: (string) $row['status'],
            title: (string) $row['title'],
            startsAtUtc: (string) $row['starts_at_utc'],
            endsAtUtc: (string) $row['ends_at_utc'],
            timezone: (string) $row['timezone'],
            location: (string) ($row['location'] ?? ''),
            note: (string) ($row['note'] ?? ''),
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
