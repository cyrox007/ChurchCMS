<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\Core\ContentRevision;
use ChurchCMS\Core\ContentRevisionRepository;
use InvalidArgumentException;
use PDO;

final class PageRevisionService
{
    private ContentRevisionRepository $revisions;
    private PageRepository $pages;

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->revisions = new ContentRevisionRepository($pdo);
        $this->pages = new PageRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(
            \ChurchCMS\Core\DatabaseManager::getInstance()
                ->connection(),
        );
    }

    public function capture(Page $page): ?ContentRevision
    {
        $snapshot = $this->snapshot($page);
        $latest = $this->revisions->forEntity(
            'page',
            $page->publicId,
            $page->siteKey,
            1,
        )[0] ?? null;

        if (
            $latest instanceof ContentRevision
            && $latest->snapshot === $snapshot
        ) {
            return null;
        }

        return $this->revisions->append(
            'page',
            $page->publicId,
            $snapshot,
            $page->siteKey,
        );
    }

    /**
     * @return list<ContentRevision>
     */
    public function history(
        string $pagePublicId,
        string $siteKey = 'default',
        int $limit = 50,
    ): array {
        return $this->revisions->forEntity(
            'page',
            $pagePublicId,
            $siteKey,
            $limit,
        );
    }

    public function restore(
        string $pagePublicId,
        string $revisionPublicId,
        string $siteKey = 'default',
    ): void {
        $page = $this->pages->findByPublicId(
            trim($pagePublicId),
            $siteKey,
        );
        $revision = $this->revisions->findByPublicId(
            trim($revisionPublicId),
            $siteKey,
        );

        if (
            $page === null
            || $revision === null
            || $revision->entityType !== 'page'
            || $revision->entityPublicId !== $page->publicId
        ) {
            throw new InvalidArgumentException(
                'Revision страницы не найден.'
            );
        }

        $snapshot = $revision->snapshot;
        $owner = self::requiredString(
            $snapshot,
            'owner_organization_public_id',
        );

        (new PageService($this->pdo))->update(
            publicId: $page->publicId,
            title: self::requiredString($snapshot, 'title'),
            slug: self::requiredString($snapshot, 'slug'),
            bodyInput: (string) ($snapshot['body_html'] ?? ''),
            navigationTitle: self::nullableString(
                $snapshot,
                'navigation_title',
            ),
            parentPublicId: self::nullableString(
                $snapshot,
                'parent_public_id',
            ),
            sortOrder: (int) ($snapshot['sort_order'] ?? 0),
            siteKey: $page->siteKey,
            ownerOrganizationPublicId: $owner,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshot(Page $page): array
    {
        $parentPublicId = null;

        if ($page->parentId !== null) {
            $parentPublicId = $this->pages->publicIdsByIds(
                [$page->parentId],
                $page->siteKey,
            )[$page->parentId] ?? null;
        }

        return [
            'snapshot_version' => 1,
            'title' => $page->title,
            'slug' => $page->slug,
            'navigation_title' => $page->navigationTitle,
            'body_html' => $page->bodyHtml,
            'parent_public_id' => $parentPublicId,
            'sort_order' => $page->sortOrder,
            'owner_organization_public_id' =>
                $page->ownerOrganizationPublicId,
        ];
    }

    private static function requiredString(
        array $snapshot,
        string $key,
    ): string {
        $value = $snapshot[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                'Revision страницы содержит неполные данные.'
            );
        }

        return $value;
    }

    private static function nullableString(
        array $snapshot,
        string $key,
    ): ?string {
        $value = $snapshot[$key] ?? null;

        return is_string($value) && trim($value) !== ''
            ? $value
            : null;
    }
}
