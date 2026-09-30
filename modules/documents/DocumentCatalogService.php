<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

final class DocumentCatalogService
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly DocumentMediaService $media,
        private readonly DocumentCategoryService $categories,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DocumentRepository::fromDatabase(),
            DocumentMediaService::fromDatabase(),
            DocumentCategoryService::fromDatabase(),
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function index(
        string $siteKey = 'default',
        int $limit = 50,
    ): array {
        $rows = [];

        foreach (
            $this->documents->publishedPublic(
                $siteKey,
                max(1, min(50, $limit)),
            ) as $document
        ) {
            $rows[] = $this->project($document);
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function detail(
        string $publicId,
        string $siteKey = 'default',
    ): ?array {
        $document = $this->documents->findByPublicId(
            trim($publicId),
            $siteKey,
        );

        if (
            $document === null
            || $document->status !== 'published'
            || $document->visibility !== 'public'
        ) {
            return null;
        }

        return $this->project($document);
    }

    /**
     * @return array<string,mixed>
     */
    private function project(
        DocumentRecord $document,
    ): array {
        $file = $this->media->publicFileDescriptor(
            $document->publicId,
            $document->siteKey,
        );
        $categories = $this->categories->forDocument(
            $document->id,
        );

        return [
            'id' => $document->publicId,
            'type' => 'document',
            'title' => $document->title,
            'document_type' => $document->documentType,
            'document_number' => $document->documentNumber,
            'issued_on' => $document->issuedOn,
            'summary' => $document->summary,
            'categories' => $categories,
            'organization_owner_id' =>
                $document->ownerOrganizationPublicId,
            'url' => '/documents/'
                . rawurlencode($document->publicId),
            'file' => $file,
            'updated_at' => $document->updatedAt,
        ];
    }
}
