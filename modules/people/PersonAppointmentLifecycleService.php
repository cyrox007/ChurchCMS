<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class PersonAppointmentLifecycleService
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

    public function update(
        string $appointmentPublicId,
        string $organizationPublicId,
        string $title,
        string $type = 'position',
        ?string $startedOn = null,
        ?string $endedOn = null,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        $appointment = $this->appointment(
            $appointmentPublicId,
            $siteKey,
        );
        $organization = $this->organizations->findByPublicId(
            trim($organizationPublicId),
            $siteKey,
        );

        if ($organization === null || $organization->status !== 'active') {
            throw new InvalidArgumentException(
                'Активная организация для назначения не найдена.'
            );
        }

        $title = trim($title);
        if ($title === '' || self::length($title) > 255) {
            throw new InvalidArgumentException(
                'Название должности обязательно и не должно превышать 255 символов.'
            );
        }

        $type = trim($type);
        if (preg_match('/^[a-z][a-z0-9_.:-]{1,63}$/D', $type) !== 1) {
            throw new InvalidArgumentException(
                'Некорректный тип назначения.'
            );
        }

        $startedOn = self::date($startedOn);
        $endedOn = self::date($endedOn);
        self::assertPeriod($startedOn, $endedOn);

        $statement = $this->pdo->prepare(
            'UPDATE person_appointments
             SET organization_public_id = :organization_public_id,
                 title = :title,
                 appointment_type = :appointment_type,
                 started_on = :started_on,
                 ended_on = :ended_on,
                 sort_order = :sort_order,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'organization_public_id' => $organization->publicId,
            'title' => $title,
            'appointment_type' => $type,
            'started_on' => $startedOn,
            'ended_on' => $endedOn,
            'sort_order' => $sortOrder,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $appointment->publicId,
            'site_key' => $siteKey,
        ]);
    }

    public function end(
        string $appointmentPublicId,
        ?string $endedOn = null,
        string $siteKey = 'default',
    ): void {
        $appointment = $this->appointment(
            $appointmentPublicId,
            $siteKey,
        );
        $endedOn = self::date($endedOn)
            ?? gmdate('Y-m-d');
        self::assertPeriod($appointment->startedOn, $endedOn);

        $statement = $this->pdo->prepare(
            'UPDATE person_appointments
             SET status = :status,
                 ended_on = :ended_on,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'status' => 'inactive',
            'ended_on' => $endedOn,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $appointment->publicId,
            'site_key' => $siteKey,
        ]);
    }

    public function reactivate(
        string $appointmentPublicId,
        string $siteKey = 'default',
    ): void {
        $appointment = $this->appointment(
            $appointmentPublicId,
            $siteKey,
        );

        $statement = $this->pdo->prepare(
            'UPDATE person_appointments
             SET status = :status,
                 ended_on = NULL,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'status' => 'active',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $appointment->publicId,
            'site_key' => $siteKey,
        ]);
    }

    private function appointment(
        string $publicId,
        string $siteKey,
    ): PersonAppointment {
        $appointment = $this->people->findAppointmentByPublicId(
            trim($publicId),
            $siteKey,
        );

        if ($appointment === null) {
            throw new InvalidArgumentException(
                'Назначение не найдено.'
            );
        }

        return $appointment;
    }

    private static function assertPeriod(
        ?string $startedOn,
        ?string $endedOn,
    ): void {
        if (
            $startedOn !== null
            && $endedOn !== null
            && $endedOn < $startedOn
        ) {
            throw new InvalidArgumentException(
                'Дата окончания назначения не может быть раньше даты начала.'
            );
        }
    }

    private static function date(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

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
