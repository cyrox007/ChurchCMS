<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class PeopleRepository
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

    public function findPersonByPublicId(
        string $publicId,
        string $siteKey = 'default',
    ): ?Person {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM people
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
            ? self::hydratePerson($row)
            : null;
    }

    /**
     * null означает глобальный доступ без фильтра владельцев.
     *
     * @param list<string>|null $ownerPublicIds
     * @return list<Person>
     */
    public function adminList(
        ?array $ownerPublicIds,
        string $siteKey = 'default',
        int $limit = 200,
    ): array {
        $limit = max(1, min(500, $limit));

        if ($ownerPublicIds === []) {
            return [];
        }

        $where = ['site_key = :site_key'];
        $params = ['site_key' => $siteKey];

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
             FROM people
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY display_name ASC, id ASC
             LIMIT ' . $limit
        );
        $statement->execute($params);

        return array_map(
            self::hydratePerson(...),
            $statement->fetchAll(),
        );
    }

    /**
     * @return list<Person>
     */
    public function activePublic(
        string $siteKey = 'default',
        int $limit = 200,
    ): array {
        $limit = max(1, min(500, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM people
             WHERE site_key = :site_key
               AND status = :status
             ORDER BY display_name ASC, id ASC
             LIMIT ' . $limit
        );
        $statement->execute([
            'site_key' => $siteKey,
            'status' => 'active',
        ]);

        return array_map(
            self::hydratePerson(...),
            $statement->fetchAll(),
        );
    }

    public function findAppointmentByPublicId(
        string $publicId,
        string $siteKey = 'default',
    ): ?PersonAppointment {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM person_appointments
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
            ? self::hydrateAppointment($row)
            : null;
    }

    /**
     * @return list<PersonAppointment>
     */
    public function appointmentsForPerson(
        string $personPublicId,
        string $siteKey = 'default',
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM person_appointments
             WHERE person_public_id = :person_public_id
               AND site_key = :site_key
             ORDER BY sort_order ASC, id ASC'
        );
        $statement->execute([
            'person_public_id' => $personPublicId,
            'site_key' => $siteKey,
        ]);

        return array_map(
            self::hydrateAppointment(...),
            $statement->fetchAll(),
        );
    }

    private static function hydratePerson(array $row): Person
    {
        return new Person(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            ownerOrganizationPublicId:
                (string) $row['owner_organization_public_id'],
            status: (string) $row['status'],
            displayName: (string) $row['display_name'],
            firstName: self::nullable($row['first_name'] ?? null),
            middleName: self::nullable($row['middle_name'] ?? null),
            lastName: self::nullable($row['last_name'] ?? null),
            biographyHtml: (string) $row['biography_html'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private static function hydrateAppointment(
        array $row,
    ): PersonAppointment {
        return new PersonAppointment(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            personPublicId:
                (string) $row['person_public_id'],
            organizationPublicId:
                (string) $row['organization_public_id'],
            title: (string) $row['title'],
            type: (string) $row['appointment_type'],
            status: (string) $row['status'],
            startedOn: self::nullable($row['started_on'] ?? null),
            endedOn: self::nullable($row['ended_on'] ?? null),
            sortOrder: (int) $row['sort_order'],
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
