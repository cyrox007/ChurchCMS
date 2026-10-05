<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationDisclosures;

final class EducationDisclosureCatalogService
{
    public function __construct(private readonly EducationDisclosureRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(EducationDisclosureRepository::fromDatabase());
    }

    /** @return list<array<string,mixed>> */
    public function index(string $siteKey = 'default'): array
    {
        return array_map(
            fn(EducationDisclosureRecord $record): array => $this->project($record),
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

    private function project(EducationDisclosureRecord $record, bool $includeBody = false): array
    {
        $data = [
            'public_id' => $record->publicId,
            'organization_owner_id' => $record->ownerOrganizationPublicId,
            'section_key' => $record->sectionKey,
            'title' => $record->title,
            'summary' => $record->summary,
            'sort_order' => $record->sortOrder,
            'url' => '/education/disclosures/' . rawurlencode($record->publicId),
            'updated_at' => $record->updatedAt,
        ];
        if ($includeBody) {
            $data['body_html'] = $record->bodyHtml;
        }
        return $data;
    }
}
