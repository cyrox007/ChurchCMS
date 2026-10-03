<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use Throwable;

final class WorshipRecurrenceService
{
    private WorshipRecurrenceRepository $rules;
    private OrganizationRepository $organizations;

    public function __construct(private readonly PDO $pdo)
    {
        $this->rules = new WorshipRecurrenceRepository($pdo);
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function createRule(
        string $title,
        string $frequency,
        string $localTime,
        string $timezone,
        string $startsOn,
        ?string $ownerOrganizationPublicId = null,
        string $siteKey = 'default',
        string $serviceType = 'service',
        ?int $weekday = null,
        ?int $durationMinutes = null,
        ?string $endsOn = null,
        ?string $locationName = null,
        string $descriptionHtml = '',
    ): string {
        $owner = $this->resolveOwner(
            $ownerOrganizationPublicId,
            $siteKey,
        );
        $title = self::requiredText(
            $title,
            255,
            'Название повторяющегося богослужения обязательно.',
        );
        $frequency = self::frequency(
            $frequency,
            $weekday,
        );
        $weekday = self::weekday(
            $frequency,
            $weekday,
        );
        $localTime = self::localTime($localTime);
        $timezone = self::timezone($timezone);
        $startsOn = self::date(
            $startsOn,
            'Некорректная дата начала повторения.',
        );
        $endsOn = self::optionalDate(
            $endsOn,
            'Некорректная дата окончания повторения.',
        );

        if ($endsOn !== null && $endsOn < $startsOn) {
            throw new InvalidArgumentException(
                'Дата окончания повторения не может быть раньше начала.'
            );
        }

        $durationMinutes = self::duration($durationMinutes);
        $serviceType = self::machineKey(
            $serviceType,
            'Некорректный тип богослужения.',
        );
        $locationName = self::optionalText(
            $locationName,
            255,
            'Название места проведения слишком длинное.',
        );

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO worship_recurrence_rules (
                public_id,
                site_key,
                owner_organization_public_id,
                status,
                title,
                service_type,
                frequency,
                weekday,
                local_time,
                timezone,
                duration_minutes,
                starts_on,
                ends_on,
                location_name,
                description_html,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :owner,
                :status,
                :title,
                :service_type,
                :frequency,
                :weekday,
                :local_time,
                :timezone,
                :duration_minutes,
                :starts_on,
                :ends_on,
                :location_name,
                :description_html,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'owner' => $owner->publicId,
            'status' => 'active',
            'title' => $title,
            'service_type' => $serviceType,
            'frequency' => $frequency,
            'weekday' => $weekday,
            'local_time' => $localTime,
            'timezone' => $timezone,
            'duration_minutes' => $durationMinutes,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'location_name' => $locationName,
            'description_html' => trim($descriptionHtml),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }

    public function deactivate(
        string $rulePublicId,
        string $siteKey = 'default',
    ): void {
        $rule = $this->rules->findByPublicId(
            trim($rulePublicId),
            $siteKey,
        );

        if ($rule === null) {
            throw new InvalidArgumentException(
                'Правило повторения не найдено.'
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE worship_recurrence_rules
             SET status = :status,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'status' => 'inactive',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $rule->publicId,
            'site_key' => $rule->siteKey,
        ]);
    }

    /**
     * @return list<string> public ID созданных богослужений
     */
    public function materialize(
        string $rulePublicId,
        string $fromDate,
        string $throughDate,
        string $siteKey = 'default',
    ): array {
        $rule = $this->rules->findByPublicId(
            trim($rulePublicId),
            $siteKey,
        );

        if ($rule === null || $rule->status !== 'active') {
            throw new InvalidArgumentException(
                'Активное правило повторения не найдено.'
            );
        }

        $fromDate = self::date(
            $fromDate,
            'Некорректная начальная дата генерации.',
        );
        $throughDate = self::date(
            $throughDate,
            'Некорректная конечная дата генерации.',
        );

        if ($throughDate < $fromDate) {
            throw new InvalidArgumentException(
                'Конечная дата генерации раньше начальной.'
            );
        }

        $start = max($fromDate, $rule->startsOn);
        $end = $rule->endsOn === null
            ? $throughDate
            : min($throughDate, $rule->endsOn);

        if ($end < $start) {
            return [];
        }

        $created = [];

        foreach ($this->dates($rule, $start, $end) as $date) {
            $publicId = $this->materializeDate(
                $rule,
                $date,
            );

            if ($publicId !== null) {
                $created[] = $publicId;
            }
        }

        return $created;
    }

    public function materializeActiveRules(
        string $fromDate,
        string $throughDate,
        string $siteKey = 'default',
    ): int {
        $count = 0;

        foreach ($this->rules->activeRules($siteKey) as $rule) {
            $count += count($this->materialize(
                $rule->publicId,
                $fromDate,
                $throughDate,
                $siteKey,
            ));
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function dates(
        WorshipRecurrenceRule $rule,
        string $start,
        string $end,
    ): array {
        $first = new DateTimeImmutable($start);
        $last = new DateTimeImmutable($end);

        $period = new DatePeriod(
            $first,
            new DateInterval('P1D'),
            $last->modify('+1 day'),
        );

        $dates = [];

        foreach ($period as $date) {
            if (
                $rule->frequency === 'weekly'
                && (int) $date->format('N') !== $rule->weekday
            ) {
                continue;
            }

            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    private function materializeDate(
        WorshipRecurrenceRule $rule,
        string $date,
    ): ?string {
        if ($this->rules->occurrenceExists(
            $rule->id,
            $date,
        )) {
            return null;
        }

        $timezone = new DateTimeZone($rule->timezone);
        $localStart = new DateTimeImmutable(
            $date . ' ' . $rule->localTime . ':00',
            $timezone,
        );
        $utcStart = $localStart
            ->setTimezone(new DateTimeZone('UTC'));

        $utcEnd = $rule->durationMinutes === null
            ? null
            : $utcStart->modify(
                '+' . $rule->durationMinutes . ' minutes',
            );

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            if ($this->rules->occurrenceExists(
                $rule->id,
                $date,
            )) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }

                return null;
            }

            $worshipPublicId = (new WorshipScheduleService(
                $this->pdo,
            ))->create(
                title: $rule->title,
                startsAt: $utcStart->format('Y-m-d H:i:s'),
                ownerOrganizationPublicId:
                    $rule->ownerOrganizationPublicId,
                siteKey: $rule->siteKey,
                serviceType: $rule->serviceType,
                endsAt: $utcEnd?->format('Y-m-d H:i:s'),
                locationName: $rule->locationName,
                descriptionHtml: $rule->descriptionHtml,
            );

            $this->rules->recordOccurrence(
                $rule->id,
                $date,
                $worshipPublicId,
            );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $worshipPublicId;
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

    private function resolveOwner(
        ?string $publicId,
        string $siteKey,
    ): \ChurchCMS\Modules\Organizations\OrganizationUnit {
        $publicId = trim((string) ($publicId ?? ''));

        $owner = $publicId !== ''
            ? $this->organizations->findByPublicId(
                $publicId,
                $siteKey,
            )
            : $this->organizations->siteRoot($siteKey);

        if ($owner === null || $owner->status !== 'active') {
            throw new InvalidArgumentException(
                'Активная организация для правила повторения не найдена.'
            );
        }

        return $owner;
    }

    private static function frequency(
        string $frequency,
        ?int $weekday,
    ): string {
        $frequency = strtolower(trim($frequency));

        if (!in_array($frequency, ['daily', 'weekly'], true)) {
            throw new InvalidArgumentException(
                'Поддерживаются ежедневные и еженедельные повторения.'
            );
        }

        if ($frequency === 'weekly' && $weekday === null) {
            throw new InvalidArgumentException(
                'Для еженедельного повторения нужен день недели.'
            );
        }

        return $frequency;
    }

    private static function weekday(
        string $frequency,
        ?int $weekday,
    ): ?int {
        if ($frequency === 'daily') {
            return null;
        }

        if ($weekday === null || $weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException(
                'День недели должен быть от 1 до 7.'
            );
        }

        return $weekday;
    }

    private static function localTime(string $value): string
    {
        $value = trim($value);

        if (
            preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Локальное время должно быть в формате ЧЧ:ММ.'
            );
        }

        return $value;
    }

    private static function timezone(string $value): string
    {
        $value = trim($value);

        try {
            new DateTimeZone($value);
        } catch (Throwable $error) {
            throw new InvalidArgumentException(
                'Некорректный часовой пояс.',
                0,
                $error,
            );
        }

        return $value;
    }

    private static function duration(?int $value): ?int
    {
        if ($value === null || $value === 0) {
            return null;
        }

        if ($value < 1 || $value > 1440) {
            throw new InvalidArgumentException(
                'Продолжительность должна быть от 1 до 1440 минут.'
            );
        }

        return $value;
    }

    private static function date(
        string $value,
        string $message,
    ): string {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value,
        );
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
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private static function optionalDate(
        ?string $value,
        string $message,
    ): ?string {
        $value = trim((string) ($value ?? ''));

        return $value === ''
            ? null
            : self::date($value, $message);
    }

    private static function requiredText(
        string $value,
        int $limit,
        string $message,
    ): string {
        $value = trim($value);

        if (
            $value === ''
            || self::length($value) > $limit
        ) {
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

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
