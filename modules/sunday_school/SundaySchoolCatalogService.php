<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\SundaySchool;

final class SundaySchoolCatalogService
{
    public function __construct(private readonly SundaySchoolRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(SundaySchoolRepository::fromDatabase());
    }

    /** @return list<array<string,mixed>> */
    public function index(string $siteKey = 'default'): array
    {
        return array_map(
            fn(SundaySchoolRecord $record): array => $this->project($record),
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

    private function project(SundaySchoolRecord $record, bool $includeDescription = false): array
    {
        $data = [
            'public_id' => $record->publicId,
            'organization_owner_id' => $record->ownerOrganizationPublicId,
            'title' => $record->title,
            'leader_name' => $record->leaderName,
            'location_name' => $record->locationName,
            'contact_email' => $record->contactEmail,
            'contact_phone' => $record->contactPhone,
            'age_info' => $record->ageInfo,
            'summary' => $record->summary,
            'sort_order' => $record->sortOrder,
            'url' => '/sunday-school/' . rawurlencode($record->publicId),
            'updated_at' => $record->updatedAt,
        ];

        if ($includeDescription) {
            $data['description_html'] = $record->descriptionHtml;
        }

        return $data;
    }
}
