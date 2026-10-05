<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Saints;

final class SaintCatalogService
{
    public function __construct(private readonly SaintRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(SaintRepository::fromDatabase());
    }

    /** @return list<array<string,mixed>> */
    public function index(string $siteKey = 'default'): array
    {
        return array_map(
            fn(SaintRecord $record): array => $this->project($record),
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

    private function project(SaintRecord $record, bool $includeBiography = false): array
    {
        $data = [
            'public_id' => $record->publicId,
            'organization_owner_id' => $record->ownerOrganizationPublicId,
            'saint_rank' => $record->saintRank,
            'display_name' => $record->displayName,
            'secular_name' => $record->secularName,
            'commemoration_text' => $record->commemorationText,
            'summary' => $record->summary,
            'sort_order' => $record->sortOrder,
            'url' => '/saints/' . rawurlencode($record->publicId),
            'updated_at' => $record->updatedAt,
        ];

        if ($includeBiography) {
            $data['biography_html'] = $record->biographyHtml;
        }

        return $data;
    }
}
