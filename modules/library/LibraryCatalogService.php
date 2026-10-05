<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Library;

final class LibraryCatalogService
{
    public function __construct(private readonly LibraryItemRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(LibraryItemRepository::fromDatabase());
    }

    /** @return list<array<string,mixed>> */
    public function index(string $siteKey = 'default'): array
    {
        return array_map(
            fn(LibraryItemRecord $record): array => $this->project($record),
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

    private function project(LibraryItemRecord $record, bool $includeDescription = false): array
    {
        $data = [
            'public_id' => $record->publicId,
            'organization_owner_id' => $record->ownerOrganizationPublicId,
            'title' => $record->title,
            'author_name' => $record->authorName,
            'publisher_name' => $record->publisherName,
            'publication_year' => $record->publicationYear,
            'isbn' => $record->isbn,
            'shelf_code' => $record->shelfCode,
            'availability_note' => $record->availabilityNote,
            'summary' => $record->summary,
            'sort_order' => $record->sortOrder,
            'url' => '/library/' . rawurlencode($record->publicId),
            'updated_at' => $record->updatedAt,
        ];

        if ($includeDescription) {
            $data['description_html'] = $record->descriptionHtml;
        }

        return $data;
    }
}
