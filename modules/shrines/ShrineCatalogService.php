<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Shrines;

final class ShrineCatalogService
{
    public function __construct(private readonly ShrineRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(ShrineRepository::fromDatabase());
    }

    /** @return list<array<string,mixed>> */
    public function index(string $siteKey = 'default'): array
    {
        return array_map(
            fn(ShrineRecord $record): array => $this->project($record),
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

    private function project(ShrineRecord $record, bool $includeDescription = false): array
    {
        $data = [
            'public_id' => $record->publicId,
            'organization_owner_id' => $record->ownerOrganizationPublicId,
            'shrine_type' => $record->shrineType,
            'title' => $record->title,
            'subtitle' => $record->subtitle,
            'location_name' => $record->locationName,
            'summary' => $record->summary,
            'sort_order' => $record->sortOrder,
            'url' => '/shrines/' . rawurlencode($record->publicId),
            'updated_at' => $record->updatedAt,
        ];

        if ($includeDescription) {
            $data['description_html'] = $record->descriptionHtml;
        }

        return $data;
    }
}
