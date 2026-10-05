<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Ministries;

use ChurchCMS\Core\HtmlSanitizer;
use ChurchCMS\Core\Uuid;
use InvalidArgumentException;

final class MinistryService
{
    public function __construct(private readonly MinistryRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(MinistryRepository::fromDatabase());
    }

    public function createDraft(
        string $ownerOrganizationPublicId,
        string $title,
        ?string $shortTitle,
        ?string $leaderName,
        ?string $contactEmail,
        ?string $contactPhone,
        string $summary,
        string $descriptionInput,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): string {
        $now = gmdate('Y-m-d H:i:s');
        $data = $this->normalized(
            $ownerOrganizationPublicId,
            $title,
            $shortTitle,
            $leaderName,
            $contactEmail,
            $contactPhone,
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
        ?string $shortTitle,
        ?string $leaderName,
        ?string $contactEmail,
        ?string $contactPhone,
        string $summary,
        string $descriptionInput,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        if ($this->repository->find($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Служение не найдено.');
        }

        $data = $this->normalized(
            $ownerOrganizationPublicId,
            $title,
            $shortTitle,
            $leaderName,
            $contactEmail,
            $contactPhone,
            $summary,
            $descriptionInput,
            $sortOrder,
        );
        $data['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->repository->update($publicId, $data, $siteKey);
    }

    public function publish(string $publicId, string $siteKey = 'default'): void
    {
        $this->setStatus($publicId, 'published', $siteKey);
    }

    public function unpublish(string $publicId, string $siteKey = 'default'): void
    {
        $this->setStatus($publicId, 'draft', $siteKey);
    }

    private function setStatus(string $publicId, string $status, string $siteKey): void
    {
        if ($this->repository->find($publicId, $siteKey) === null) {
            throw new InvalidArgumentException('Служение не найдено.');
        }
        $this->repository->setStatus($publicId, $status, $siteKey);
    }

    private function normalized(
        string $ownerOrganizationPublicId,
        string $title,
        ?string $shortTitle,
        ?string $leaderName,
        ?string $contactEmail,
        ?string $contactPhone,
        string $summary,
        string $descriptionInput,
        int $sortOrder,
    ): array {
        $ownerOrganizationPublicId = trim($ownerOrganizationPublicId);
        $title = trim($title);
        if ($ownerOrganizationPublicId === '') {
            throw new InvalidArgumentException('Не выбрана организация-владелец.');
        }
        if ($title === '' || mb_strlen($title) > 255) {
            throw new InvalidArgumentException('Название служения должно содержать от 1 до 255 символов.');
        }
        $contactEmail = self::nullable($contactEmail, 255);
        if ($contactEmail !== null && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Некорректный email служения.');
        }

        return [
            'owner_organization_public_id' => $ownerOrganizationPublicId,
            'title' => $title,
            'short_title' => self::nullable($shortTitle, 160),
            'leader_name' => self::nullable($leaderName, 255),
            'contact_email' => $contactEmail,
            'contact_phone' => self::nullable($contactPhone, 64),
            'summary' => mb_substr(trim($summary), 0, 2000),
            'description_html' => HtmlSanitizer::fromEditorInput($descriptionInput),
            'sort_order' => max(-100000, min(100000, $sortOrder)),
        ];
    }

    private static function nullable(?string $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
