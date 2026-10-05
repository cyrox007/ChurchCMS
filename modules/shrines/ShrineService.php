<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Shrines;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\HtmlSanitizer;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationUnit;
use InvalidArgumentException;
use PDO;

final class ShrineService
{
    private const TYPES = [
        'relics',
        'icon',
        'place',
        'object',
        'spring',
        'other',
    ];

    private ShrineRepository $repository;
    private OrganizationRepository $organizations;

    public function __construct(PDO $pdo)
    {
        $this->repository = new ShrineRepository($pdo);
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function createDraft(
        string $ownerOrganizationPublicId,
        string $shrineType,
        string $title,
        ?string $subtitle,
        ?string $locationName,
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
            $shrineType,
            $title,
            $subtitle,
            $locationName,
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
        string $shrineType,
        string $title,
        ?string $subtitle,
        ?string $locationName,
        string $summary,
        string $descriptionInput,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        if ($this->repository->find($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Святыня не найдена.');
        }
        $owner = $this->organization($ownerOrganizationPublicId, $siteKey);
        $data = $this->normalized(
            $owner->publicId,
            $shrineType,
            $title,
            $subtitle,
            $locationName,
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
            throw new InvalidArgumentException('Святыня не найдена.');
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
        string $shrineType,
        string $title,
        ?string $subtitle,
        ?string $locationName,
        string $summary,
        string $descriptionInput,
        int $sortOrder,
    ): array {
        $shrineType = trim($shrineType);
        if (!in_array($shrineType, self::TYPES, true)) {
            throw new InvalidArgumentException('Неизвестный тип святыни.');
        }
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 255) {
            throw new InvalidArgumentException('Название святыни должно содержать от 1 до 255 символов.');
        }

        return [
            'owner_organization_public_id' => $ownerOrganizationPublicId,
            'shrine_type' => $shrineType,
            'title' => $title,
            'subtitle' => self::nullable($subtitle, 255),
            'location_name' => self::nullable($locationName, 255),
            'summary' => mb_substr(trim($summary), 0, 2000),
            'description_html' => HtmlSanitizer::fromEditorInput($descriptionInput),
            'sort_order' => max(-100000, min(100000, $sortOrder)),
        ];
    }

    private static function siteKey(string $siteKey): string
    {
        $siteKey = trim($siteKey);
        if ($siteKey === '' || strlen($siteKey) > 64) {
            throw new InvalidArgumentException('Некорректный site_key.');
        }
        return $siteKey;
    }

    private static function nullable(?string $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
