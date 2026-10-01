<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use Throwable;

final class WorshipHolidayTemplateService
{
    private WorshipHolidayTemplateRepository $templates;
    private OrganizationRepository $organizations;

    public function __construct(private readonly PDO $pdo)
    {
        $this->templates = new WorshipHolidayTemplateRepository(
            $pdo,
        );
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function createTemplate(
        string $name,
        string $timezone,
        string $siteKey = 'default',
    ): string {
        $name = self::requiredText(
            $name,
            255,
            'Название праздничного шаблона обязательно.',
        );
        $timezone = self::timezone($timezone);

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO worship_holiday_templates (
                public_id,
                site_key,
                name,
                timezone,
                status,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :name,
                :timezone,
                :status,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'name' => $name,
            'timezone' => $timezone,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }

    public function addItem(
        string $templatePublicId,
        string $title,
        int $dayOffset,
        string $localTime,
        string $siteKey = 'default',
        string $serviceType = 'service',
        ?int $durationMinutes = null,
        ?string $locationName = null,
        string $descriptionHtml = '',
        int $sortOrder = 0,
    ): int {
        $template = $this->templateOrFail(
            $templatePublicId,
            $siteKey,
        );

        $title = self::requiredText(
            $title,
            255,
            'Название службы в праздничном шаблоне обязательно.',
        );
        $localTime = self::localTime($localTime);
        $serviceType = self::machineKey(
            $serviceType,
            'Некорректный тип службы в праздничном шаблоне.',
        );
        $durationMinutes = self::duration(
            $durationMinutes,
        );
        $locationName = self::optionalText(
            $locationName,
            255,
            'Название места проведения слишком длинное.',
        );

        if ($dayOffset < -30 || $dayOffset > 30) {
            throw new InvalidArgumentException(
                'Смещение службы относительно праздника должно быть от -30 до 30 дней.'
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO worship_holiday_template_items (
                template_id,
                sort_order,
                title,
                service_type,
                day_offset,
                local_time,
                duration_minutes,
                location_name,
                description_html
             ) VALUES (
                :template_id,
                :sort_order,
                :title,
                :service_type,
                :day_offset,
                :local_time,
                :duration_minutes,
                :location_name,
                :description_html
             )'
        );
        $statement->execute([
            'template_id' => (int) $template['id'],
            'sort_order' => $sortOrder,
            'title' => $title,
            'service_type' => $serviceType,
            'day_offset' => $dayOffset,
            'local_time' => $localTime,
            'duration_minutes' => $durationMinutes,
            'location_name' => $locationName,
            'description_html' => trim($descriptionHtml),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return list<string> public ID созданных богослужений
     */
    public function apply(
        string $templatePublicId,
        string $ownerOrganizationPublicId,
        string $feastDate,
        string $siteKey = 'default',
    ): array {
        $template = $this->templateOrFail(
            $templatePublicId,
            $siteKey,
        );
        $owner = $this->organizations->findByPublicId(
            trim($ownerOrganizationPublicId),
            $siteKey,
        );

        if ($owner === null || $owner->status !== 'active') {
            throw new InvalidArgumentException(
                'Активная организация для праздничного шаблона не найдена.'
            );
        }

        $feastDate = self::date(
            $feastDate,
            'Некорректная дата праздника.',
        );
        $timezone = new DateTimeZone(
            (string) $template['timezone'],
        );
        $items = $this->templates->items(
            (int) $template['id'],
        );

        $created = [];

        foreach ($items as $item) {
            $publicId = $this->applyItem(
                $template,
                $item,
                $owner->publicId,
                $feastDate,
                $timezone,
            );

            if ($publicId !== null) {
                $created[] = $publicId;
            }
        }

        return $created;
    }

    private function applyItem(
        array $template,
        array $item,
        string $ownerPublicId,
        string $feastDate,
        DateTimeZone $timezone,
    ): ?string {
        $itemId = (int) $item['id'];
        $siteKey = (string) $template['site_key'];

        if ($this->templates->applicationExists(
            $itemId,
            $siteKey,
            $ownerPublicId,
            $feastDate,
        )) {
            return null;
        }

        $localDate = (new DateTimeImmutable(
            $feastDate,
            $timezone,
        ))->modify(
            ((int) $item['day_offset'] >= 0 ? '+' : '')
            . (int) $item['day_offset']
            . ' days',
        );

        $localStart = new DateTimeImmutable(
            $localDate->format('Y-m-d')
            . ' '
            . (string) $item['local_time']
            . ':00',
            $timezone,
        );
        $utcStart = $localStart->setTimezone(
            new DateTimeZone('UTC'),
        );

        $duration = isset($item['duration_minutes'])
            ? (int) $item['duration_minutes']
            : null;
        $utcEnd = $duration === null
            ? null
            : $utcStart->modify('+' . $duration . ' minutes');

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            if ($this->templates->applicationExists(
                $itemId,
                $siteKey,
                $ownerPublicId,
                $feastDate,
            )) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }

                return null;
            }

            $worshipPublicId = (new WorshipScheduleService(
                $this->pdo,
            ))->create(
                title: (string) $item['title'],
                startsAt: $utcStart->format('Y-m-d H:i:s'),
                ownerOrganizationPublicId: $ownerPublicId,
                siteKey: $siteKey,
                serviceType: (string) $item['service_type'],
                endsAt: $utcEnd?->format('Y-m-d H:i:s'),
                locationName: self::nullable(
                    $item['location_name'] ?? null,
                ),
                descriptionHtml:
                    (string) $item['description_html'],
            );

            $this->templates->recordApplication(
                (int) $template['id'],
                $itemId,
                $siteKey,
                $ownerPublicId,
                $feastDate,
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

    /** @return array<string,mixed> */
    private function templateOrFail(
        string $publicId,
        string $siteKey,
    ): array {
        $template = $this->templates->find(
            trim($publicId),
            $siteKey,
        );

        if (
            $template === null
            || ($template['status'] ?? '') !== 'active'
        ) {
            throw new InvalidArgumentException(
                'Активный праздничный шаблон не найден.'
            );
        }

        return $template;
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
                'Время в праздничном шаблоне должно быть в формате ЧЧ:ММ.'
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
                'Некорректный часовой пояс праздничного шаблона.',
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
                'Продолжительность службы должна быть от 1 до 1440 минут.'
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

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
