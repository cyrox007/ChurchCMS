<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationUnit;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use Throwable;

final class WorshipScheduleService
{
    private WorshipRepository $worship;
    private OrganizationRepository $organizations;

    public function __construct(private readonly PDO $pdo)
    {
        $this->worship = new WorshipRepository($pdo);
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function create(
        string $title,
        string $startsAt,
        ?string $ownerOrganizationPublicId = null,
        string $siteKey = 'default',
        string $serviceType = 'service',
        ?string $endsAt = null,
        ?string $locationName = null,
        string $descriptionHtml = '',
    ): string {
        $siteKey = self::siteKey($siteKey);
        $owner = $this->resolveOrganization(
            $ownerOrganizationPublicId,
            $siteKey,
        );
        $title = self::requiredText(
            $title,
            255,
            'Название богослужения обязательно.',
        );
        $serviceType = self::machineKey(
            $serviceType,
            'Некорректный тип богослужения.',
        );
        $startsAt = self::timestamp(
            $startsAt,
            'Некорректное время начала богослужения.',
        );
        $endsAt = self::optionalTimestamp(
            $endsAt,
            'Некорректное время окончания богослужения.',
        );
        $locationName = self::optionalText(
            $locationName,
            255,
            'Название места проведения слишком длинное.',
        );

        if (
            $endsAt !== null
            && new DateTimeImmutable($endsAt, new DateTimeZone('UTC'))
                < new DateTimeImmutable(
                    $startsAt,
                    new DateTimeZone('UTC'),
                )
        ) {
            throw new InvalidArgumentException(
                'Окончание богослужения не может быть раньше начала.'
            );
        }

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO worship_services (
                public_id,
                site_key,
                owner_organization_public_id,
                status,
                title,
                service_type,
                starts_at,
                ends_at,
                location_name,
                description_html,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :owner_organization_public_id,
                :status,
                :title,
                :service_type,
                :starts_at,
                :ends_at,
                :location_name,
                :description_html,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'owner_organization_public_id' => $owner->publicId,
            'status' => 'scheduled',
            'title' => $title,
            'service_type' => $serviceType,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'location_name' => $locationName,
            'description_html' => trim($descriptionHtml),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }

    public function assignOrganizationOwner(
        string $worshipPublicId,
        string $organizationPublicId,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        $service = $this->worship->findByPublicId(
            $worshipPublicId,
            $siteKey,
        );

        if ($service === null) {
            throw new InvalidArgumentException(
                'Богослужение не найдено.'
            );
        }

        $organization = $this->resolveOrganization(
            $organizationPublicId,
            $siteKey,
        );

        $statement = $this->pdo->prepare(
            'UPDATE worship_services
             SET owner_organization_public_id = :organization_id,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'organization_id' => $organization->publicId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $service->publicId,
            'site_key' => $siteKey,
        ]);
    }

    public function cancel(
        string $worshipPublicId,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        $service = $this->worship->findByPublicId(
            $worshipPublicId,
            $siteKey,
        );

        if ($service === null) {
            throw new InvalidArgumentException(
                'Богослужение не найдено.'
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE worship_services
             SET status = :status,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'status' => 'cancelled',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $service->publicId,
            'site_key' => $siteKey,
        ]);
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
                'Активная организация для богослужения не найдена.'
            );
        }

        return $organization;
    }

    private static function siteKey(string $value): string
    {
        $value = trim($value);

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9_.-]{0,63}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный site key расписания богослужений.'
            );
        }

        return $value;
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
        string $message,
    ): ?string {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (self::length($value) > $limit) {
            throw new InvalidArgumentException($message);
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

    private static function timestamp(
        string $value,
        string $message,
    ): string {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        try {
            return (new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC'),
            ))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (Throwable $error) {
            throw new InvalidArgumentException(
                $message,
                0,
                $error,
            );
        }
    }

    private static function optionalTimestamp(
        ?string $value,
        string $message,
    ): ?string {
        $value = trim((string) ($value ?? ''));

        return $value === ''
            ? null
            : self::timestamp(
                $value,
                $message,
            );
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
