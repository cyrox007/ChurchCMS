<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\ContentRevision;
use ChurchCMS\Core\ContentRevisionRepository;
use ChurchCMS\Core\DatabaseManager;
use InvalidArgumentException;
use PDO;

final class PublicationRevisionService
{
    private ContentRevisionRepository $revisions;
    private PublicationRepository $publications;
    private PublicationTaxonomyService $taxonomy;

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->revisions = new ContentRevisionRepository($pdo);
        $this->publications = new PublicationRepository($pdo);
        $this->taxonomy = new PublicationTaxonomyService($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function capture(
        Publication $publication,
    ): ?ContentRevision {
        $snapshot = $this->snapshot($publication);
        $latest = $this->revisions->forEntity(
            'publication',
            $publication->publicId,
            $publication->siteKey,
            1,
        )[0] ?? null;

        if (
            $latest instanceof ContentRevision
            && $latest->snapshot === $snapshot
        ) {
            return null;
        }

        return $this->revisions->append(
            'publication',
            $publication->publicId,
            $snapshot,
            $publication->siteKey,
        );
    }

    /**
     * @return list<ContentRevision>
     */
    public function history(
        string $publicationPublicId,
        string $siteKey = 'default',
        int $limit = 50,
    ): array {
        return $this->revisions->forEntity(
            'publication',
            $publicationPublicId,
            $siteKey,
            $limit,
        );
    }

    public function revision(
        string $publicationPublicId,
        string $revisionPublicId,
        string $siteKey = 'default',
    ): ContentRevision {
        $revision = $this->revisions->findByPublicId(
            trim($revisionPublicId),
            $siteKey,
        );

        if (
            $revision === null
            || $revision->entityType !== 'publication'
            || $revision->entityPublicId !== trim($publicationPublicId)
        ) {
            throw new InvalidArgumentException(
                'Revision публикации не найден.'
            );
        }

        return $revision;
    }

    public function restore(
        string $publicationPublicId,
        string $revisionPublicId,
        string $siteKey = 'default',
    ): void {
        $publication = $this->publications->findByPublicId(
            trim($publicationPublicId),
            $siteKey,
        );
        if ($publication === null) {
            throw new InvalidArgumentException(
                'Публикация не найдена.'
            );
        }

        $revision = $this->revision(
            $publication->publicId,
            $revisionPublicId,
            $siteKey,
        );

        $snapshot = $revision->snapshot;
        $type = PublicationType::tryFrom(
            self::requiredString($snapshot, 'type'),
        );

        if ($type === null) {
            throw new InvalidArgumentException(
                'Revision публикации содержит неизвестный тип.'
            );
        }

        (new PublicationService($this->pdo))->update(
            publicId: $publication->publicId,
            title: self::requiredString($snapshot, 'title'),
            slug: self::requiredString($snapshot, 'slug'),
            type: $type,
            excerpt: (string) ($snapshot['excerpt'] ?? ''),
            bodyHtml: (string) ($snapshot['body_html'] ?? ''),
            authorName: self::nullableString(
                $snapshot,
                'author_name',
            ),
            syndicationTargets: self::stringList(
                $snapshot['syndication_targets'] ?? [],
            ),
            commentsEnabled:
                (bool) ($snapshot['comments_enabled'] ?? false),
            siteKey: $publication->siteKey,
            categoryNames: self::stringList(
                $snapshot['categories'] ?? [],
            ),
            tagNames: self::stringList(
                $snapshot['tags'] ?? [],
            ),
            ownerOrganizationPublicId: self::requiredString(
                $snapshot,
                'owner_organization_public_id',
            ),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshot(
        Publication $publication,
    ): array {
        $taxonomy = $this->taxonomy->forPublication(
            $publication->id,
        );

        return [
            'snapshot_version' => 1,
            'type' => $publication->type->value,
            'slug' => $publication->slug,
            'title' => $publication->title,
            'excerpt' => $publication->excerpt,
            'body_html' => $publication->bodyHtml,
            'author_name' => $publication->authorName,
            'syndication_targets' =>
                $publication->syndicationTargets,
            'comments_enabled' =>
                $publication->commentsEnabled,
            'owner_organization_public_id' =>
                $publication->ownerOrganizationPublicId,
            'categories' => self::termNames(
                $taxonomy['categories'] ?? [],
            ),
            'tags' => self::termNames(
                $taxonomy['tags'] ?? [],
            ),
        ];
    }

    private static function requiredString(
        array $snapshot,
        string $key,
    ): string {
        $value = $snapshot[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                'Revision публикации содержит неполные данные.'
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

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn(mixed $item): string =>
                    is_string($item) ? trim($item) : '',
                $value,
            ),
            static fn(string $item): bool => $item !== '',
        ));
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return list<string>
     */
    private static function termNames(array $terms): array
    {
        return array_values(array_filter(
            array_map(
                static fn(array $term): string =>
                    trim((string) ($term['name'] ?? '')),
                $terms,
            ),
            static fn(string $name): bool => $name !== '',
        ));
    }
}
