<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationSchedules;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\EducationPrograms\EducationProgramRepository;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use PDO;

final class EducationScheduleService
{
    private EducationScheduleRepository $repository;
    private EducationProgramRepository $programs;

    public function __construct(PDO $pdo)
    {
        $this->repository = new EducationScheduleRepository($pdo);
        $this->programs = new EducationProgramRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function createDraft(
        string $programPublicId,
        string $title,
        string $startsAtLocal,
        string $endsAtLocal,
        string $timezone,
        string $location,
        string $note,
        string $siteKey = 'default',
    ): string {
        $siteKey = self::siteKey($siteKey);
        $program = $this->programs->find(trim($programPublicId), $siteKey);
        if ($program === null) {
            throw new InvalidArgumentException('Образовательная программа не найдена.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $data = $this->normalized($program->publicId, $title, $startsAtLocal, $endsAtLocal, $timezone, $location, $note);
        $data += [
            'public_id' => Uuid::v4(),
            'site_key' => $siteKey,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        return $this->repository->create($data);
    }

    public function update(
        string $publicId,
        string $programPublicId,
        string $title,
        string $startsAtLocal,
        string $endsAtLocal,
        string $timezone,
        string $location,
        string $note,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        if ($this->repository->find($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Запись расписания не найдена.');
        }
        $program = $this->programs->find(trim($programPublicId), $siteKey);
        if ($program === null) {
            throw new InvalidArgumentException('Образовательная программа не найдена.');
        }
        $data = $this->normalized($program->publicId, $title, $startsAtLocal, $endsAtLocal, $timezone, $location, $note);
        $data['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->repository->update($publicId, $data, $siteKey);
    }

    public function publish(string $publicId, string $siteKey = 'default'): void
    {
        $this->setStatus($publicId, 'published', self::siteKey($siteKey));
    }

    public function unpublish(string $publicId, string $siteKey = 'default'): void
    {
        $this->setStatus($publicId, 'draft', self::siteKey($siteKey));
    }

    private function setStatus(string $publicId, string $status, string $siteKey): void
    {
        if ($this->repository->find($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Запись расписания не найдена.');
        }
        $this->repository->setStatus($publicId, $status, $siteKey);
    }

    private function normalized(
        string $programPublicId,
        string $title,
        string $startsAtLocal,
        string $endsAtLocal,
        string $timezone,
        string $location,
        string $note,
    ): array {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 500) {
            throw new InvalidArgumentException('Название занятия должно содержать от 1 до 500 символов.');
        }
        $timezone = trim($timezone);
        try {
            $zone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw new InvalidArgumentException('Укажите корректный часовой пояс.');
        }
        $starts = self::localDateTime($startsAtLocal, $zone, 'Начало занятия');
        $ends = self::localDateTime($endsAtLocal, $zone, 'Окончание занятия');
        if ($ends <= $starts) {
            throw new InvalidArgumentException('Окончание занятия должно быть позже начала.');
        }
        $utc = new DateTimeZone('UTC');
        return [
            'program_public_id' => $programPublicId,
            'title' => $title,
            'starts_at_utc' => $starts->setTimezone($utc)->format('Y-m-d H:i:s'),
            'ends_at_utc' => $ends->setTimezone($utc)->format('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'location' => mb_substr(trim($location), 0, 500),
            'note' => mb_substr(trim($note), 0, 4000),
        ];
    }

    private static function localDateTime(string $value, DateTimeZone $timezone, string $label): DateTimeImmutable
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, $timezone);
        if ($date === false || $date->format('Y-m-d\\TH:i') !== $value) {
            throw new InvalidArgumentException($label . ' указано некорректно.');
        }
        return $date;
    }

    private static function siteKey(string $siteKey): string
    {
        $siteKey = trim($siteKey);
        if ($siteKey === '' || strlen($siteKey) > 64) {
            throw new InvalidArgumentException('Некорректный site_key.');
        }
        return $siteKey;
    }
}
