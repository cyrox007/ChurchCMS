<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationScience;

final class EducationScienceCatalogService
{
    public function __construct(private readonly EducationScienceRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(EducationScienceRepository::fromDatabase());
    }

    /** @return list<array<string,mixed>> */
    public function index(string $siteKey = 'default'): array
    {
        return array_map(
            fn(EducationScienceRecord $record): array => $this->project($record),
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

    private function project(EducationScienceRecord $record, bool $includeDescription = false): array
    {
        $data = [
            'public_id' => $record->publicId,
            'organization_owner_id' => $record->ownerOrganizationPublicId,
            'activity_type' => $record->activityType,
            'title' => $record->title,
            'starts_on' => $record->startsOn,
            'ends_on' => $record->endsOn,
            'summary' => $record->summary,
            'external_url' => $record->externalUrl,
            'sort_order' => $record->sortOrder,
            'url' => '/education/science/' . rawurlencode($record->publicId),
            'updated_at' => $record->updatedAt,
        ];
        if ($includeDescription) {
            $data['description_html'] = $record->descriptionHtml;
        }
        return $data;
    }
}
