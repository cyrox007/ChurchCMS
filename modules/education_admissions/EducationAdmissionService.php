<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationAdmissions;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\EducationPrograms\EducationProgramRepository;
use InvalidArgumentException;
use PDO;

final class EducationAdmissionService
{
    private EducationAdmissionRepository $repository;
    private EducationProgramRepository $programs;

    public function __construct(PDO $pdo)
    {
        $this->repository = new EducationAdmissionRepository($pdo);
        $this->programs = new EducationProgramRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function createDraft(
        string $programPublicId,
        string $title,
        string $academicYear,
        ?string $startsOn,
        ?string $endsOn,
        int $budgetSeats,
        int $paidSeats,
        string $tuitionNote,
        string $requirements,
        string $entranceTests,
        string $contactNote,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): string {
        $siteKey = self::siteKey($siteKey);
        $program = $this->programs->find(trim($programPublicId), $siteKey);
        if ($program === null) {
            throw new InvalidArgumentException('Образовательная программа не найдена.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $data = $this->normalized(
            $program->publicId,
            $title,
            $academicYear,
            $startsOn,
            $endsOn,
            $budgetSeats,
            $paidSeats,
            $tuitionNote,
            $requirements,
            $entranceTests,
            $contactNote,
            $sortOrder,
        );
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
        string $academicYear,
        ?string $startsOn,
        ?string $endsOn,
        int $budgetSeats,
        int $paidSeats,
        string $tuitionNote,
        string $requirements,
        string $entranceTests,
        string $contactNote,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        if ($this->repository->find($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Запись приёмной кампании не найдена.');
        }
        $program = $this->programs->find(trim($programPublicId), $siteKey);
        if ($program === null) {
            throw new InvalidArgumentException('Образовательная программа не найдена.');
        }

        $data = $this->normalized(
            $program->publicId,
            $title,
            $academicYear,
            $startsOn,
            $endsOn,
            $budgetSeats,
            $paidSeats,
            $tuitionNote,
            $requirements,
            $entranceTests,
            $contactNote,
            $sortOrder,
        );
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
            throw new InvalidArgumentException('Запись приёмной кампании не найдена.');
        }
        $this->repository->setStatus($publicId, $status, $siteKey);
    }

    private function normalized(
        string $programPublicId,
        string $title,
        string $academicYear,
        ?string $startsOn,
        ?string $endsOn,
        int $budgetSeats,
        int $paidSeats,
        string $tuitionNote,
        string $requirements,
        string $entranceTests,
        string $contactNote,
        int $sortOrder,
    ): array {
        $title = self::required($title, 500, 'Укажите название приёмной кампании.');
        $academicYear = self::required($academicYear, 32, 'Укажите учебный год.');
        $startsOn = self::date($startsOn, 'Дата начала приёма');
        $endsOn = self::date($endsOn, 'Дата окончания приёма');
        if ($startsOn !== null && $endsOn !== null && $endsOn < $startsOn) {
            throw new InvalidArgumentException('Дата окончания приёма не может быть раньше даты начала.');
        }
        if ($budgetSeats < 0 || $budgetSeats > 100000 || $paidSeats < 0 || $paidSeats > 100000) {
            throw new InvalidArgumentException('Количество мест должно быть от 0 до 100000.');
        }

        return [
            'program_public_id' => $programPublicId,
            'title' => $title,
            'academic_year' => $academicYear,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'budget_seats' => $budgetSeats,
            'paid_seats' => $paidSeats,
            'tuition_note' => mb_substr(trim($tuitionNote), 0, 4000),
            'requirements' => mb_substr(trim($requirements), 0, 8000),
            'entrance_tests' => mb_substr(trim($entranceTests), 0, 8000),
            'contact_note' => mb_substr(trim($contactNote), 0, 4000),
            'sort_order' => max(-100000, min(100000, $sortOrder)),
        ];
    }

    private static function required(string $value, int $max, string $message): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException($message);
        }
        return $value;
    }

    private static function date(?string $value, string $label): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($label . ' указана некорректно.');
        }
        return $value;
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
