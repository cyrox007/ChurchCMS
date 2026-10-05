<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Ministries;

final class MinistryCatalogService
{
    public function __construct(private readonly MinistryRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(MinistryRepository::fromDatabase());
    }

    /** @return list<array<string,mixed>> */
    public function index(string $siteKey = 'default'): array
    {
        return array_map(
            fn(MinistryRecord $record): array => $this->project($record),
            $this->repository->published($siteKey),
        );
    }

    /** @return array<string,mixed>|null */
    public function detail(string $publicId, string $siteKey = 'default'): ?array
    {
        $record = $this->repository->find($publicId, $siteKey);
        if ($record === null || $record->status !== 'published') {
            return null;
        }

        return $this->project($record, true);
    }

    /** @return array<string,mixed> */
    private function project(MinistryRecord $record, bool $includeDescription = false): array
    {
        $data = [
            'public_id' => $record->publicId,
            'organization_owner_id' => $record->ownerOrganizationPublicId,
            'title' => $record->title,
            'short_title' => $record->shortTitle,
            'leader_name' => $record->leaderName,
            'contact_email' => $record->contactEmail,
            'contact_phone' => $record->contactPhone,
            'summary' => $record->summary,
            'sort_order' => $record->sortOrder,
            'url' => '/ministries/' . rawurlencode($record->publicId),
            'updated_at' => $record->updatedAt,
        ];

        if ($includeDescription) {
            $data['description_html'] = $record->descriptionHtml;
        }

        return $data;
    }
}
