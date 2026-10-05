<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationDisclosures;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\HtmlSanitizer;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use InvalidArgumentException;
use PDO;

final class EducationDisclosureService
{
    private EducationDisclosureRepository $repository;
    private OrganizationRepository $organizations;

    public function __construct(PDO $pdo)
    {
        $this->repository = new EducationDisclosureRepository($pdo);
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function createDraft(
        string $ownerOrganizationPublicId,
        string $sectionKey,
        string $title,
        string $summary,
        string $bodyInput,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): string {
        $siteKey = self::siteKey($siteKey);
        $owner = $this->organizations->findByPublicId(trim($ownerOrganizationPublicId), $siteKey);
        if ($owner === null || $owner->status !== 'active') {
            throw new InvalidArgumentException('Организация-владелец недоступна.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $data = $this->normalized($owner->publicId, $sectionKey, $title, $summary, $bodyInput, $sortOrder);
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
        string $sectionKey,
        string $title,
        string $summary,
        string $bodyInput,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        if ($this->repository->find($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Раздел обязательных сведений не найден.');
        }
        $owner = $this->organizations->findByPublicId(trim($ownerOrganizationPublicId), $siteKey);
        if ($owner === null || $owner->status !== 'active') {
            throw new InvalidArgumentException('Организация-владелец недоступна.');
        }
        $data = $this->normalized($owner->publicId, $sectionKey, $title, $summary, $bodyInput, $sortOrder);
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
            throw new InvalidArgumentException('Раздел обязательных сведений не найден.');
        }
        $this->repository->setStatus($publicId, $status, $siteKey);
    }

    private function normalized(
        string $ownerOrganizationPublicId,
        string $sectionKey,
        string $title,
        string $summary,
        string $bodyInput,
        int $sortOrder,
    ): array {
        $sectionKey = strtolower(trim($sectionKey));
        if ($sectionKey === '' || strlen($sectionKey) > 100 || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $sectionKey) !== 1) {
            throw new InvalidArgumentException('Ключ раздела должен содержать латинские буквы, цифры, точку, дефис или подчёркивание.');
        }
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 500) {
            throw new InvalidArgumentException('Название раздела должно содержать от 1 до 500 символов.');
        }
        return [
            'owner_organization_public_id' => $ownerOrganizationPublicId,
            'section_key' => $sectionKey,
            'title' => $title,
            'summary' => mb_substr(trim($summary), 0, 2000),
            'body_html' => HtmlSanitizer::fromEditorInput($bodyInput),
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
}
