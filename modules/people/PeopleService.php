<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationUnit;
use InvalidArgumentException;
use PDO;

final class PeopleService
{
    private PeopleRepository $people;
    private OrganizationRepository $organizations;

    public function __construct(private readonly PDO $pdo)
    {
        $this->people = new PeopleRepository($pdo);
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function createPerson(
        string $displayName,
        ?string $ownerOrganizationPublicId = null,
        string $siteKey = 'default',
        ?string $firstName = null,
        ?string $middleName = null,
        ?string $lastName = null,
        string $biographyHtml = '',
    ): string {
        $displayName = self::requiredText(
            $displayName,
            255,
            'Отображаемое имя человека обязательно.',
        );
        $owner = $this->resolveOrganization(
            $ownerOrganizationPublicId,
            $siteKey,
        );
        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO people (
                public_id,
                site_key,
                owner_organization_public_id,
                status,
                display_name,
                first_name,
                middle_name,
                last_name,
                biography_html,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :owner_organization_public_id,
                :status,
                :display_name,
                :first_name,
                :middle_name,
                :last_name,
                :biography_html,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'owner_organization_public_id' => $owner->publicId,
            'status' => 'active',
            'display_name' => $displayName,
            'first_name' => self::optionalText($firstName, 120),
            'middle_name' => self::optionalText($middleName, 120),
            'last_name' => self::optionalText($lastName, 120),
            'biography_html' => trim($biographyHtml),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }

    public function updatePerson(
        string $personPublicId,
        string $displayName,
        ?string $firstName = null,
        ?string $middleName = null,
        ?string $lastName = null,
        string $biographyHtml = '',
        string $siteKey = 'default',
    ): void {
        $person = $this->people->findPersonByPublicId(
            trim($personPublicId),
            $siteKey,
        );

        if ($person === null || $person->status !== 'active') {
            throw new InvalidArgumentException(
                'Карточка человека недоступна для редактирования.'
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE people
             SET display_name = :display_name,
                 first_name = :first_name,
                 middle_name = :middle_name,
                 last_name = :last_name,
                 biography_html = :biography_html,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'display_name' => self::requiredText(
                $displayName,
                255,
                'Отображаемое имя человека обязательно.',
            ),
            'first_name' => self::optionalText($firstName, 120),
            'middle_name' => self::optionalText($middleName, 120),
            'last_name' => self::optionalText($lastName, 120),
            'biography_html' => trim($biographyHtml),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $person->publicId,
            'site_key' => $siteKey,
        ]);
    }

    public function assignOrganizationOwner(
        string $personPublicId,
        string $organizationPublicId,
        string $siteKey = 'default',
    ): void {
        $person = $this->people->findPersonByPublicId(
            $personPublicId,
            $siteKey,
        );

        if ($person === null) {
            throw new InvalidArgumentException(
                'Человек не найден.'
            );
        }

        $organization = $this->resolveOrganization(
            $organizationPublicId,
            $siteKey,
        );

        $statement = $this->pdo->prepare(
            'UPDATE people
             SET owner_organization_public_id = :organization_id,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'organization_id' => $organization->publicId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $personPublicId,
            'site_key' => $siteKey,
        ]);
    }

    public function createAppointment(
        string $personPublicId,
        string $organizationPublicId,
        string $title,
        string $type = 'position',
        ?string $startedOn = null,
        ?string $endedOn = null,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): string {
        $person = $this->people->findPersonByPublicId(
            $personPublicId,
            $siteKey,
        );

        if ($person === null) {
            throw new InvalidArgumentException(
                'Человек для назначения не найден.'
            );
        }

        $organization = $this->resolveOrganization(
            $organizationPublicId,
            $siteKey,
        );
        $title = self::requiredText(
            $title,
            255,
            'Название должности обязательно.',
        );
        $type = self::machineKey(
            $type,
            'Некорректный тип назначения.',
        );
        $startedOn = self::date($startedOn);
        $endedOn = self::date($endedOn);

        if (
            $startedOn !== null
            && $endedOn !== null
            && $endedOn < $startedOn
        ) {
            throw new InvalidArgumentException(
                'Дата окончания назначения не может быть раньше даты начала.'
            );
        }

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO person_appointments (
                public_id,
                site_key,
                person_public_id,
                organization_public_id,
                title,
                appointment_type,
                status,
                started_on,
                ended_on,
                sort_order,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :person_public_id,
                :organization_public_id,
                :title,
                :appointment_type,
                :status,
                :started_on,
                :ended_on,
                :sort_order,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'person_public_id' => $person->publicId,
            'organization_public_id' => $organization->publicId,
            'title' => $title,
            'appointment_type' => $type,
            'status' => 'active',
            'started_on' => $startedOn,
            'ended_on' => $endedOn,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }

    private function resolveOrganization(
        ?string $publicId,
        string $siteKey,
    ): OrganizationUnit {
        $publicId = trim((string) ($publicId ?? ''));

        $organization = $publicId !== ''
            ? $this->organizations->findByPublicId(
                $publicId,
                $siteKey,
            )
            : $this->organizations->siteRoot($siteKey);

        if (
            $organization === null
            || $organization->status !== 'active'
        ) {
            throw new InvalidArgumentException(
                'Активная организация для человека или назначения не найдена.'
            );
        }

        return $organization;
    }

    private static function requiredText(
        string $value,
        int $limit,
        string $message,
    ): string {
        $value = trim($value);

        if ($value === '' || self::length($value) > $limit) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private static function optionalText(
        ?string $value,
        int $limit,
    ): ?string {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (self::length($value) > $limit) {
            throw new InvalidArgumentException(
                'Значение поля человека слишком длинное.'
            );
        }

        return $value;
    }

    private static function machineKey(
        string $value,
        string $message,
    ): string {
        $value = trim($value);

        if (
            preg_match(
                '/^[a-z][a-z0-9_.:-]{1,63}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private static function date(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value,
        );
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    ($errors['warning_count'] ?? 0) > 0
                    || ($errors['error_count'] ?? 0) > 0
                )
            )
            || $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException(
                'Некорректная дата назначения.'
            );
        }

        return $value;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
