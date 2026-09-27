<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\SyndicationEntry;
use ChurchCMS\Core\SyndicationProvider;

final class PublicationSyndicationProvider implements SyndicationProvider
{
    public function __construct(private readonly string $siteKey = 'default')
    {
    }

    public function entries(): iterable
    {
        $base = rtrim((string) Config::get('syndication.site_url', ''), '/');
        if ($base === '') {
            return [];
        }

        $result = [];
        $repository = PublicationRepository::fromDatabase();
        $publications = $repository->published(
            $this->siteKey,
            100,
            0,
        );
        $taxonomy = PublicationTaxonomyService::fromDatabase()
            ->forPublications(array_map(
                static fn(Publication $publication): int =>
                    $publication->id,
                $publications,
            ));

        foreach ($publications as $publication) {
            if (
                $publication->syndicationTargets === []
                || $publication->publishedAt === null
            ) {
                continue;
            }

            $categories = [
                $publication->type->value,
            ];

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

            $result[] = new SyndicationEntry(
                id: $publication->publicId,
                url: $base . '/publications/' . rawurlencode($publication->slug),
                title: $publication->syndicationTitle ?? $publication->title,
                description: $publication->syndicationExcerpt ?? $publication->excerpt,
                contentHtml: $publication->bodyHtml,
                publishedAt: $publication->publishedAt,
                updatedAt: $publication->updatedAt,
                author: $publication->authorName,
                categories: $categories,
                targets: $publication->syndicationTargets,
            );
        }

        return $result;
    }
}
