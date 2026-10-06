<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\SyndicationEntry;
use ChurchCMS\Core\TargetAwareSyndicationProvider;

final class PublicationSyndicationProvider implements TargetAwareSyndicationProvider
{
    public function __construct(private readonly string $siteKey = 'default')
    {
    }

    public function entries(): iterable
    {
        return $this->buildEntries(null);
    }

    public function entriesForTarget(string $target): iterable
    {
        return $this->buildEntries($target);
    }

    /** @return list<SyndicationEntry> */
    private function buildEntries(?string $target): array
    {
        $base = rtrim((string) Config::get('syndication.site_url', ''), '/');
        if ($base === '') {
            return [];
        }

        $repository = PublicationRepository::fromDatabase();
        $publications = $repository->published(
            $this->siteKey,
            100,
            0,
        );
        $publicationIds = array_map(
            static fn(Publication $publication): int => $publication->id,
            $publications,
        );
        $taxonomy = PublicationTaxonomyService::fromDatabase()
            ->forPublications($publicationIds);
        $overrides = $target === null
            ? []
            : PublicationSyndicationOverrideRepository::fromDatabase()
                ->forTarget($publicationIds, $target);

        $result = [];
        foreach ($publications as $publication) {
            if (
                $publication->syndicationTargets === []
                || $publication->publishedAt === null
            ) {
                continue;
            }

            $categories = [$publication->type->value];
            foreach (
                $taxonomy[$publication->id]['categories'] ?? []
                as $category
            ) {
                $name = trim((string) ($category['name'] ?? ''));
                if ($name !== '') {
                    $categories[] = $name;
                }
            }
            $categories = array_values(array_unique($categories));

            $override = $overrides[$publication->id] ?? null;
            $result[] = new SyndicationEntry(
                id: $publication->publicId,
                url: $base . '/publications/' . rawurlencode($publication->slug),
                title: $override['title']
                    ?? $publication->syndicationTitle
                    ?? $publication->title,
                description: $override['excerpt']
                    ?? $publication->syndicationExcerpt
                    ?? $publication->excerpt,
                contentHtml: $publication->bodyHtml,
                publishedAt: $publication->publishedAt,
                updatedAt: $publication->updatedAt,
                author: $publication->authorName,
                categories: $categories,
                imageUrl: $override['image_url'] ?? null,
                imageMime: $override['image_mime'] ?? null,
                targets: $publication->syndicationTargets,
            );
        }

        return $result;
    }
}
