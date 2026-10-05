<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationStaff;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class EducationStaffRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /** @return list<EducationChairRecord> */
    public function adminChairs(?array $ownerPublicIds = null, string $siteKey = 'default'): array
    {
        $sql = 'SELECT * FROM education_chairs WHERE site_key = :site_key';
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

        $sql .= ' ORDER BY sort_order ASC, name ASC, id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_map(self::chair(...), $statement->fetchAll());
    }

    /** @return list<EducationChairRecord> */
    public function publishedChairs(string $siteKey = 'default'): array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM education_chairs
             WHERE site_key = :site_key AND status = 'published'
             ORDER BY sort_order ASC, name ASC, id ASC"
        );
        $statement->execute(['site_key' => $siteKey]);
        return array_map(self::chair(...), $statement->fetchAll());
    }

    public function findChair(string $publicId, string $siteKey = 'default'): ?EducationChairRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM education_chairs WHERE site_key = :site_key AND public_id = :public_id LIMIT 1'
        );
        $statement->execute(['site_key' => $siteKey, 'public_id' => $publicId]);
        $row = $statement->fetch();
        return is_array($row) ? self::chair($row) : null;
    }

    public function createChair(array $data): string
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO education_chairs (
                public_id, site_key, owner_organization_public_id, status, name,
                short_name, description_html, sort_order, created_at, updated_at
             ) VALUES (
                :public_id, :site_key, :owner_organization_public_id, :status, :name,
                :short_name, :description_html, :sort_order, :created_at, :updated_at
             )'
        );
        $statement->execute($data);
        return (string) $data['public_id'];
    }

    public function updateChair(string $publicId, array $data, string $siteKey = 'default'): void
    {
        $data['public_id'] = $publicId;
        $data['site_key'] = $siteKey;
        $statement = $this->pdo->prepare(
            'UPDATE education_chairs SET
                owner_organization_public_id = :owner_organization_public_id,
                name = :name,
                short_name = :short_name,
                description_html = :description_html,
                sort_order = :sort_order,
                updated_at = :updated_at
             WHERE site_key = :site_key AND public_id = :public_id'
        );
        $statement->execute($data);
    }

    public function setChairStatus(string $publicId, string $status, string $siteKey = 'default'): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE education_chairs SET status = :status, updated_at = :updated_at
             WHERE site_key = :site_key AND public_id = :public_id'
        );
        $statement->execute([
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'site_key' => $siteKey,
            'public_id' => $publicId,
        ]);
    }

    /** @return list<array{public_id:string,display_name:string,owner_organization_public_id:string}> */
    public function activePeople(?array $ownerPublicIds = null, string $siteKey = 'default'): array
    {
        $sql = "SELECT public_id, display_name, owner_organization_public_id
                FROM people
                WHERE site_key = :site_key AND status = 'active'";
        $params = ['site_key' => $siteKey];

        if ($ownerPublicIds !== null) {
            if ($ownerPublicIds === []) {
                return [];
            }
            $placeholders = [];
            foreach (array_values($ownerPublicIds) as $index => $ownerPublicId) {
                $key = 'person_owner_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $ownerPublicId;
            }
            $sql .= ' AND owner_organization_public_id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY display_name ASC, id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(
            static fn(array $row): array => [
                'public_id' => (string) $row['public_id'],
                'display_name' => (string) $row['display_name'],
                'owner_organization_public_id' => (string) $row['owner_organization_public_id'],
            ],
            $statement->fetchAll(),
        );
    }

    /** @return list<EducationTeacherAssignmentRecord> */
    public function teachers(string $chairPublicId, bool $onlyActive = false, string $siteKey = 'default'): array
    {
        $sql = 'SELECT a.*, p.display_name AS person_display_name
                FROM education_teacher_assignments a
                INNER JOIN people p
                    ON p.site_key = a.site_key AND p.public_id = a.person_public_id
                WHERE a.site_key = :site_key AND a.chair_public_id = :chair_public_id';
        if ($onlyActive) {
            $sql .= " AND a.status = 'active' AND p.status = 'active'";
        }
        $sql .= ' ORDER BY a.sort_order ASC, p.display_name ASC, a.id ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'site_key' => $siteKey,
            'chair_public_id' => $chairPublicId,
        ]);
        return array_map(self::teacher(...), $statement->fetchAll());
    }

    public function findTeacher(string $publicId, string $siteKey = 'default'): ?EducationTeacherAssignmentRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT a.*, p.display_name AS person_display_name
             FROM education_teacher_assignments a
             INNER JOIN people p
                ON p.site_key = a.site_key AND p.public_id = a.person_public_id
             WHERE a.site_key = :site_key AND a.public_id = :public_id
             LIMIT 1'
        );
        $statement->execute(['site_key' => $siteKey, 'public_id' => $publicId]);
        $row = $statement->fetch();
        return is_array($row) ? self::teacher($row) : null;
    }

    public function createTeacher(array $data): string
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO education_teacher_assignments (
                public_id, site_key, chair_public_id, person_public_id, status,
                position_title, academic_degree, academic_title, disciplines,
                sort_order, created_at, updated_at
             ) VALUES (
                :public_id, :site_key, :chair_public_id, :person_public_id, :status,
                :position_title, :academic_degree, :academic_title, :disciplines,
                :sort_order, :created_at, :updated_at
             )'
        );
        $statement->execute($data);
        return (string) $data['public_id'];
    }

    public function updateTeacher(string $publicId, array $data, string $siteKey = 'default'): void
    {
        $data['public_id'] = $publicId;
        $data['site_key'] = $siteKey;
        $statement = $this->pdo->prepare(
            'UPDATE education_teacher_assignments SET
                person_public_id = :person_public_id,
                position_title = :position_title,
                academic_degree = :academic_degree,
                academic_title = :academic_title,
                disciplines = :disciplines,
                sort_order = :sort_order,
                updated_at = :updated_at
             WHERE site_key = :site_key AND public_id = :public_id'
        );
        $statement->execute($data);
    }

    public function setTeacherStatus(string $publicId, string $status, string $siteKey = 'default'): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE education_teacher_assignments SET status = :status, updated_at = :updated_at
             WHERE site_key = :site_key AND public_id = :public_id'
        );
        $statement->execute([
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'site_key' => $siteKey,
            'public_id' => $publicId,
        ]);
    }

    public function activePersonExists(string $personPublicId, string $siteKey = 'default'): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM people
             WHERE site_key = :site_key AND public_id = :public_id AND status = 'active'
             LIMIT 1"
        );
        $statement->execute(['site_key' => $siteKey, 'public_id' => $personPublicId]);
        return $statement->fetchColumn() !== false;
    }

    private static function chair(array $row): EducationChairRecord
    {
        return new EducationChairRecord(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            ownerOrganizationPublicId: (string) $row['owner_organization_public_id'],
            status: (string) $row['status'],
            name: (string) $row['name'],
            shortName: self::nullable($row['short_name'] ?? null),
            descriptionHtml: (string) ($row['description_html'] ?? ''),
            sortOrder: (int) ($row['sort_order'] ?? 0),
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private static function teacher(array $row): EducationTeacherAssignmentRecord
    {
        return new EducationTeacherAssignmentRecord(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            chairPublicId: (string) $row['chair_public_id'],
            personPublicId: (string) $row['person_public_id'],
            personDisplayName: (string) $row['person_display_name'],
            status: (string) $row['status'],
            positionTitle: (string) $row['position_title'],
            academicDegree: self::nullable($row['academic_degree'] ?? null),
            academicTitle: self::nullable($row['academic_title'] ?? null),
            disciplines: (string) ($row['disciplines'] ?? ''),
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
