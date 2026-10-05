<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationStaff;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\HtmlSanitizer;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use InvalidArgumentException;
use PDO;

final class EducationStaffService
{
    private EducationStaffRepository $repository;
    private OrganizationRepository $organizations;

    public function __construct(PDO $pdo)
    {
        $this->repository = new EducationStaffRepository($pdo);
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function createChair(
        string $ownerOrganizationPublicId,
        string $name,
        ?string $shortName,
        string $descriptionInput,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): string {
        $siteKey = self::siteKey($siteKey);
        $owner = $this->organizations->findByPublicId(trim($ownerOrganizationPublicId), $siteKey);
        if ($owner === null || $owner->status !== 'active') {
            throw new InvalidArgumentException('Организация-владелец кафедры недоступна.');
        }

        $now = gmdate('Y-m-d H:i:s');
        return $this->repository->createChair([
            'public_id' => Uuid::v4(),
            'site_key' => $siteKey,
            'owner_organization_public_id' => $owner->publicId,
            'status' => 'draft',
            'name' => self::required($name, 500, 'Укажите название кафедры.'),
            'short_name' => self::nullable($shortName, 120),
            'description_html' => HtmlSanitizer::fromEditorInput($descriptionInput),
            'sort_order' => self::sortOrder($sortOrder),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateChair(
        string $publicId,
        string $ownerOrganizationPublicId,
        string $name,
        ?string $shortName,
        string $descriptionInput,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        if ($this->repository->findChair($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Кафедра не найдена.');
        }
        $owner = $this->organizations->findByPublicId(trim($ownerOrganizationPublicId), $siteKey);
        if ($owner === null || $owner->status !== 'active') {
            throw new InvalidArgumentException('Организация-владелец кафедры недоступна.');
        }

        $this->repository->updateChair($publicId, [
            'owner_organization_public_id' => $owner->publicId,
            'name' => self::required($name, 500, 'Укажите название кафедры.'),
            'short_name' => self::nullable($shortName, 120),
            'description_html' => HtmlSanitizer::fromEditorInput($descriptionInput),
            'sort_order' => self::sortOrder($sortOrder),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], $siteKey);
    }

    public function publishChair(string $publicId, string $siteKey = 'default'): void
    {
        $this->chairStatus($publicId, 'published', self::siteKey($siteKey));
    }

    public function unpublishChair(string $publicId, string $siteKey = 'default'): void
    {
        $this->chairStatus($publicId, 'draft', self::siteKey($siteKey));
    }

    public function assignTeacher(
        string $chairPublicId,
        string $personPublicId,
        string $positionTitle,
        ?string $academicDegree,
        ?string $academicTitle,
        string $disciplines,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): string {
        $siteKey = self::siteKey($siteKey);
        $this->requireChair($chairPublicId, $siteKey);
        $this->requirePerson($personPublicId, $siteKey);
        $now = gmdate('Y-m-d H:i:s');

        return $this->repository->createTeacher([
            'public_id' => Uuid::v4(),
            'site_key' => $siteKey,
            'chair_public_id' => $chairPublicId,
            'person_public_id' => trim($personPublicId),
            'status' => 'active',
            'position_title' => self::required($positionTitle, 255, 'Укажите должность преподавателя.'),
            'academic_degree' => self::nullable($academicDegree, 255),
            'academic_title' => self::nullable($academicTitle, 255),
            'disciplines' => mb_substr(trim($disciplines), 0, 4000),
            'sort_order' => self::sortOrder($sortOrder),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateTeacher(
        string $assignmentPublicId,
        string $personPublicId,
        string $positionTitle,
        ?string $academicDegree,
        ?string $academicTitle,
        string $disciplines,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        $assignment = $this->repository->findTeacher($assignmentPublicId, $siteKey);
        if ($assignment === null) {
            throw new InvalidArgumentException('Назначение преподавателя не найдено.');
        }
        $this->requirePerson($personPublicId, $siteKey);

        $this->repository->updateTeacher($assignmentPublicId, [
            'person_public_id' => trim($personPublicId),
            'position_title' => self::required($positionTitle, 255, 'Укажите должность преподавателя.'),
            'academic_degree' => self::nullable($academicDegree, 255),
            'academic_title' => self::nullable($academicTitle, 255),
            'disciplines' => mb_substr(trim($disciplines), 0, 4000),
            'sort_order' => self::sortOrder($sortOrder),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], $siteKey);
    }

    public function deactivateTeacher(string $assignmentPublicId, string $siteKey = 'default'): void
    {
        $this->teacherStatus($assignmentPublicId, 'inactive', self::siteKey($siteKey));
    }

    public function reactivateTeacher(string $assignmentPublicId, string $siteKey = 'default'): void
    {
        $this->teacherStatus($assignmentPublicId, 'active', self::siteKey($siteKey));
    }

    private function chairStatus(string $publicId, string $status, string $siteKey): void
    {
        $this->requireChair($publicId, $siteKey);
        $this->repository->setChairStatus($publicId, $status, $siteKey);
    }

    private function teacherStatus(string $publicId, string $status, string $siteKey): void
    {
        if ($this->repository->findTeacher($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Назначение преподавателя не найдено.');
        }
        $this->repository->setTeacherStatus($publicId, $status, $siteKey);
    }

    private function requireChair(string $publicId, string $siteKey): EducationChairRecord
    {
        $chair = $this->repository->findChair(trim($publicId), $siteKey);
        if ($chair === null) {
            throw new InvalidArgumentException('Кафедра не найдена.');
        }
        return $chair;
    }

    private function requirePerson(string $publicId, string $siteKey): void
    {
        if (!$this->repository->activePersonExists(trim($publicId), $siteKey)) {
            throw new InvalidArgumentException('Активная карточка преподавателя не найдена.');
        }
    }

    private static function required(string $value, int $max, string $message): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException($message);
        }
        return $value;
    }

    private static function nullable(?string $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private static function sortOrder(int $value): int
    {
        return max(-100000, min(100000, $value));
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
