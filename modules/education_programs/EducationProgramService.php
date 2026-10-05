<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationPrograms;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\HtmlSanitizer;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationUnit;
use InvalidArgumentException;
use PDO;

final class EducationProgramService
{
    private EducationProgramRepository $repository;
    private OrganizationRepository $organizations;

    public function __construct(PDO $pdo)
    {
        $this->repository = new EducationProgramRepository($pdo);
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function createDraft(
        string $ownerOrganizationPublicId,
        string $title,
        string $educationLevel,
        string $studyForm,
        ?int $durationMonths,
        ?string $qualification,
        string $admissionNote,
        string $summary,
        string $descriptionInput,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): string {
        $siteKey = self::siteKey($siteKey);
        $owner = $this->organization($ownerOrganizationPublicId, $siteKey);
        $now = gmdate('Y-m-d H:i:s');
        $data = $this->normalized(
            $owner->publicId,
            $title,
            $educationLevel,
            $studyForm,
            $durationMonths,
            $qualification,
            $admissionNote,
            $summary,
            $descriptionInput,
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
        string $ownerOrganizationPublicId,
        string $title,
        string $educationLevel,
        string $studyForm,
        ?int $durationMonths,
        ?string $qualification,
        string $admissionNote,
        string $summary,
        string $descriptionInput,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        if ($this->repository->find($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Образовательная программа не найдена.');
        }
        $owner = $this->organization($ownerOrganizationPublicId, $siteKey);
        $data = $this->normalized(
            $owner->publicId,
            $title,
            $educationLevel,
            $studyForm,
            $durationMonths,
            $qualification,
            $admissionNote,
            $summary,
            $descriptionInput,
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
            throw new InvalidArgumentException('Образовательная программа не найдена.');
        }
        $this->repository->setStatus($publicId, $status, $siteKey);
    }

    private function organization(string $publicId, string $siteKey): OrganizationUnit
    {
        $organization = $this->organizations->findByPublicId(trim($publicId), $siteKey);
        if ($organization === null || $organization->status !== 'active') {
            throw new InvalidArgumentException('Организация-владелец недоступна.');
        }
        return $organization;
    }

    private function normalized(
        string $ownerOrganizationPublicId,
        string $title,
        string $educationLevel,
        string $studyForm,
        ?int $durationMonths,
        ?string $qualification,
        string $admissionNote,
        string $summary,
        string $descriptionInput,
        int $sortOrder,
    ): array {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 500) {
            throw new InvalidArgumentException('Название программы должно содержать от 1 до 500 символов.');
        }

        $educationLevel = self::required($educationLevel, 100, 'Укажите уровень образования.');
        $studyForm = self::required($studyForm, 100, 'Укажите форму обучения.');
        if ($durationMonths !== null && ($durationMonths < 1 || $durationMonths > 240)) {
            throw new InvalidArgumentException('Длительность обучения должна быть от 1 до 240 месяцев.');
        }

        return [
            'owner_organization_public_id' => $ownerOrganizationPublicId,
            'title' => $title,
            'education_level' => $educationLevel,
            'study_form' => $studyForm,
            'duration_months' => $durationMonths,
            'qualification' => self::nullable($qualification, 255),
            'admission_note' => mb_substr(trim($admissionNote), 0, 4000),
            'summary' => mb_substr(trim($summary), 0, 2000),
            'description_html' => HtmlSanitizer::fromEditorInput($descriptionInput),
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

    private static function nullable(?string $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $max);
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
