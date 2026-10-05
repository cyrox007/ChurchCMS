<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationAdmissions;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class EducationAdmissionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /** @return list<EducationAdmissionRecord> */
    public function adminList(?array $ownerPublicIds = null, string $siteKey = 'default'): array
    {
        $sql = 'SELECT a.*, p.title AS program_title,
                       p.owner_organization_public_id AS program_owner_organization_public_id
                FROM education_admissions a
                INNER JOIN education_programs p
                    ON p.site_key = a.site_key AND p.public_id = a.program_public_id
                WHERE a.site_key = :site_key';
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

        $sql .= ' ORDER BY a.academic_year DESC, a.sort_order ASC, a.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    /** @return list<EducationAdmissionRecord> */
    public function published(string $siteKey = 'default'): array
    {
        $statement = $this->pdo->prepare(
            "SELECT a.*, p.title AS program_title,
                    p.owner_organization_public_id AS program_owner_organization_public_id
             FROM education_admissions a
             INNER JOIN education_programs p
                ON p.site_key = a.site_key AND p.public_id = a.program_public_id
             WHERE a.site_key = :site_key
               AND a.status = 'published'
               AND p.status = 'published'
             ORDER BY a.academic_year DESC, a.sort_order ASC, a.id ASC"
        );
        $statement->execute(['site_key' => $siteKey]);
        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    public function find(string $publicId, string $siteKey = 'default'): ?EducationAdmissionRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT a.*, p.title AS program_title,
                    p.owner_organization_public_id AS program_owner_organization_public_id
             FROM education_admissions a
             INNER JOIN education_programs p
                ON p.site_key = a.site_key AND p.public_id = a.program_public_id
             WHERE a.site_key = :site_key AND a.public_id = :public_id
             LIMIT 1'
        );
        $statement->execute(['site_key' => $siteKey, 'public_id' => $publicId]);
        $row = $statement->fetch();
        return is_array($row) ? self::hydrate($row) : null;
    }

    public function findPublished(string $publicId, string $siteKey = 'default'): ?EducationAdmissionRecord
    {
        $statement = $this->pdo->prepare(
            "SELECT a.*, p.title AS program_title,
                    p.owner_organization_public_id AS program_owner_organization_public_id
             FROM education_admissions a
             INNER JOIN education_programs p
                ON p.site_key = a.site_key AND p.public_id = a.program_public_id
             WHERE a.site_key = :site_key
               AND a.public_id = :public_id
               AND a.status = 'published'
               AND p.status = 'published'
             LIMIT 1"
        );
        $statement->execute(['site_key' => $siteKey, 'public_id' => $publicId]);
        $row = $statement->fetch();
        return is_array($row) ? self::hydrate($row) : null;
    }

    public function create(array $data): string
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO education_admissions (
                public_id, site_key, program_public_id, status, title, academic_year,
                starts_on, ends_on, budget_seats, paid_seats, tuition_note,
                requirements, entrance_tests, contact_note, sort_order, created_at, updated_at
             ) VALUES (
                :public_id, :site_key, :program_public_id, :status, :title, :academic_year,
                :starts_on, :ends_on, :budget_seats, :paid_seats, :tuition_note,
                :requirements, :entrance_tests, :contact_note, :sort_order, :created_at, :updated_at
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
            'UPDATE education_admissions SET
                program_public_id = :program_public_id,
                title = :title,
                academic_year = :academic_year,
                starts_on = :starts_on,
                ends_on = :ends_on,
                budget_seats = :budget_seats,
                paid_seats = :paid_seats,
                tuition_note = :tuition_note,
                requirements = :requirements,
                entrance_tests = :entrance_tests,
                contact_note = :contact_note,
                sort_order = :sort_order,
                updated_at = :updated_at
             WHERE site_key = :site_key AND public_id = :public_id'
        );
        $statement->execute($data);
    }

    public function setStatus(string $publicId, string $status, string $siteKey = 'default'): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE education_admissions SET status = :status, updated_at = :updated_at
             WHERE site_key = :site_key AND public_id = :public_id'
        );
        $statement->execute([
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'site_key' => $siteKey,
            'public_id' => $publicId,
        ]);
    }

    private static function hydrate(array $row): EducationAdmissionRecord
    {
        return new EducationAdmissionRecord(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            programPublicId: (string) $row['program_public_id'],
            programTitle: (string) $row['program_title'],
            programOwnerOrganizationPublicId: (string) $row['program_owner_organization_public_id'],
            status: (string) $row['status'],
            title: (string) $row['title'],
            academicYear: (string) $row['academic_year'],
            startsOn: self::nullable($row['starts_on'] ?? null),
            endsOn: self::nullable($row['ends_on'] ?? null),
            budgetSeats: (int) $row['budget_seats'],
            paidSeats: (int) $row['paid_seats'],
            tuitionNote: (string) ($row['tuition_note'] ?? ''),
            requirements: (string) ($row['requirements'] ?? ''),
            entranceTests: (string) ($row['entrance_tests'] ?? ''),
            contactNote: (string) ($row['contact_note'] ?? ''),
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
