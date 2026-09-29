<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Events;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationUnit;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use Throwable;

final class EventService
{
    private EventRepository $events;
    private OrganizationRepository $organizations;

    public function __construct(private readonly PDO $pdo)
    {
        $this->events = new EventRepository($pdo);
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
        ?string $endsAt = null,
        bool $allDay = false,
        ?string $locationName = null,
        string $excerpt = '',
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
            'Название события обязательно.',
        );
        $startsAt = self::timestamp(
            $startsAt,
            'Некорректное время начала события.',
        );
        $endsAt = self::optionalTimestamp(
            $endsAt,
            'Некорректное время окончания события.',
        );
        $locationName = self::optionalText(
            $locationName,
            255,
            'Название места события слишком длинное.',
        );
        $excerpt = self::text(
            $excerpt,
            2000,
            'Краткое описание события слишком длинное.',
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
                'Окончание события не может быть раньше начала.'
            );
        }

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO events (
                public_id,
                site_key,
                owner_organization_public_id,
                status,
                title,
                starts_at,
                ends_at,
                all_day,
                location_name,
                excerpt,
                description_html,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :owner_organization_public_id,
                :status,
                :title,
                :starts_at,
                :ends_at,
                :all_day,
                :location_name,
                :excerpt,
                :description_html,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'owner_organization_public_id' => $owner->publicId,
            'status' => 'draft',
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => $allDay ? 1 : 0,
            'location_name' => $locationName,
            'excerpt' => $excerpt,
            'description_html' => trim($descriptionHtml),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }

    public function assignOrganizationOwner(
        string $eventPublicId,
        string $organizationPublicId,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        $event = $this->events->findByPublicId(
            $eventPublicId,
            $siteKey,
        );

        if ($event === null) {
            throw new InvalidArgumentException(
                'Событие не найдено.'
            );
        }

        $organization = $this->resolveOrganization(
            $organizationPublicId,
            $siteKey,
        );

        $statement = $this->pdo->prepare(
            'UPDATE events
             SET owner_organization_public_id = :organization_id,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'organization_id' => $organization->publicId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $event->publicId,
            'site_key' => $siteKey,
        ]);
    }

    public function publish(
        string $eventPublicId,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        $event = $this->eventOrFail(
            $eventPublicId,
            $siteKey,
        );
        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            (new EventPartnerTombstoneRepository(
                $this->pdo,
            ))->clear($event);

            $this->updateStatus(
                $event,
                'published',
                gmdate('Y-m-d H:i:s'),
            );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if (
                $ownsTransaction
                && $this->pdo->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    public function withdraw(
        string $eventPublicId,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        $event = $this->eventOrFail(
            $eventPublicId,
            $siteKey,
        );

        $this->leavePublishedState(
            $event,
            'withdrawn',
            'withdrawn',
        );
    }

    public function cancel(
        string $eventPublicId,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        $event = $this->eventOrFail(
            $eventPublicId,
            $siteKey,
        );

        $this->leavePublishedState(
            $event,
            'cancelled',
            'cancelled',
        );
    }

    private function leavePublishedState(
        Event $event,
        string $nextStatus,
        string $reason,
    ): void {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $updatedAt = new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            );

            if ($event->status === 'published') {
                (new EventPartnerTombstoneRepository(
                    $this->pdo,
                ))->record(
                    $event,
                    $reason,
                    $updatedAt,
                );
            }

            $this->updateStatus(
                $event,
                $nextStatus,
                $updatedAt->format('Y-m-d H:i:s'),
            );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if (
                $ownsTransaction
                && $this->pdo->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    private function updateStatus(
        Event $event,
        string $status,
        string $updatedAt,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE events
             SET status = :status,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'status' => $status,
            'updated_at' => $updatedAt,
            'public_id' => $event->publicId,
            'site_key' => $event->siteKey,
        ]);
    }

    private function eventOrFail(
        string $eventPublicId,
        string $siteKey,
    ): Event {
        $event = $this->events->findByPublicId(
            $eventPublicId,
            $siteKey,
        );

        if ($event === null) {
            throw new InvalidArgumentException(
                'Событие не найдено.'
            );
        }

        return $event;
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
                'Активная организация для события не найдена.'
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
                'Некорректный site key событий.'
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

    private static function text(
        string $value,
        int $limit,
        string $message,
    ): string {
        $value = trim($value);

        if (self::length($value) > $limit) {
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
