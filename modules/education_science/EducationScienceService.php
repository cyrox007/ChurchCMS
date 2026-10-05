<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationScience;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\HtmlSanitizer;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class EducationScienceService
{
    private const TYPES = ['research', 'conference', 'publication', 'grant', 'laboratory', 'other'];

    private EducationScienceRepository $repository;
    private OrganizationRepository $organizations;

    public function __construct(PDO $pdo)
    {
        $this->repository = new EducationScienceRepository($pdo);
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function createDraft(
        string $ownerOrganizationPublicId,
        string $activityType,
        string $title,
        ?string $startsOn,
        ?string $endsOn,
        string $summary,
        string $descriptionInput,
        ?string $externalUrl,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): string {
        $siteKey = self::siteKey($siteKey);
        $owner = $this->organizations->findByPublicId(trim($ownerOrganizationPublicId), $siteKey);
        if ($owner === null || $owner->status !== 'active') {
            throw new InvalidArgumentException('Организация-владелец недоступна.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $data = $this->normalized(
            $owner->publicId,
            $activityType,
            $title,
            $startsOn,
            $endsOn,
            $summary,
            $descriptionInput,
            $externalUrl,
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
        string $activityType,
        string $title,
        ?string $startsOn,
        ?string $endsOn,
        string $summary,
        string $descriptionInput,
        ?string $externalUrl,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        if ($this->repository->find($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Запись научной деятельности не найдена.');
        }
        $owner = $this->organizations->findByPublicId(trim($ownerOrganizationPublicId), $siteKey);
        if ($owner === null || $owner->status !== 'active') {
            throw new InvalidArgumentException('Организация-владелец недоступна.');
        }
        $data = $this->normalized(
            $owner->publicId,
            $activityType,
            $title,
            $startsOn,
            $endsOn,
            $summary,
            $descriptionInput,
            $externalUrl,
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
            throw new InvalidArgumentException('Запись научной деятельности не найдена.');
        }
        $this->repository->setStatus($publicId, $status, $siteKey);
    }

    private function normalized(
        string $ownerOrganizationPublicId,
        string $activityType,
        string $title,
        ?string $startsOn,
        ?string $endsOn,
        string $summary,
        string $descriptionInput,
        ?string $externalUrl,
        int $sortOrder,
    ): array {
        $activityType = trim($activityType);
        if (!in_array($activityType, self::TYPES, true)) {
            throw new InvalidArgumentException('Некорректный тип научной активности.');
        }
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 500) {
            throw new InvalidArgumentException('Название должно содержать от 1 до 500 символов.');
        }
        $startsOn = self::date($startsOn, 'Дата начала');
        $endsOn = self::date($endsOn, 'Дата окончания');
        if ($startsOn !== null && $endsOn !== null && $endsOn < $startsOn) {
            throw new InvalidArgumentException('Дата окончания не может быть раньше даты начала.');
        }
        $externalUrl = self::externalUrl($externalUrl);
        return [
            'owner_organization_public_id' => $ownerOrganizationPublicId,
            'activity_type' => $activityType,
            'title' => $title,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'summary' => mb_substr(trim($summary), 0, 2000),
            'description_html' => HtmlSanitizer::fromEditorInput($descriptionInput),
            'external_url' => $externalUrl,
            'sort_order' => max(-100000, min(100000, $sortOrder)),
        ];
    }

    private static function date(?string $value, string $label): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($label . ' указана некорректно.');
        }
        return $value;
    }

    private static function externalUrl(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (strlen($value) > 2000 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Некорректная внешняя ссылка.');
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if ($scheme !== 'https') {
            throw new InvalidArgumentException('Внешняя ссылка должна использовать HTTPS.');
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
