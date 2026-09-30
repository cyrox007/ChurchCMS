<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\SeoRenderer;
use ChurchCMS\Core\ThemeRenderer;

final class PublicationsController
{
    public function index(Request $request): never
    {
        $repository = PublicationRepository::fromDatabase();
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;
        $siteName = (string) Config::get('site.name', Config::get('app.name', 'ChurchCMS'));

        $seo = [
            'title' => 'Публикации — ' . $siteName,
            'description' => 'Новости, статьи, объявления и другие опубликованные материалы ' . $siteName . '.',
            'canonical' => SeoRenderer::absoluteUrl('/publications'),
            'og_type' => 'website',
            'index' => true,
            'follow' => true,
        ];

        $publications = $repository->published(
            'default',
            $perPage,
            $offset,
        );
        $taxonomy = PublicationTaxonomyService::fromDatabase()
            ->forPublications(array_map(
                static fn(Publication $publication): int =>
                    $publication->id,
                $publications,
            ));

        ThemeRenderer::fromConfig()->page('publication.index', [
            'title' => $seo['title'],
            'heading' => 'Публикации',
            'publications' => $publications,
            'taxonomy' => $taxonomy,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $repository->countPublished('default'),
            'seo' => $seo,
        ]);
    }

    public function show(Request $request, string $slug): never
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
            Response::text('404 Not Found', 404);
        }

        $publication = PublicationRepository::fromDatabase()->findPublishedBySlug($slug);
        if ($publication === null) {
            Response::text('404 Not Found', 404);
        }

        $taxonomy = PublicationTaxonomyService::fromDatabase()
            ->forPublication($publication->id);

        $commentsAvailable = false;
        $commentsOpen = false;
        $comments = [];

        $capability = ModuleRuntimeLoader::capability(
            'comments',
            'comments.publication',
        );

        if (
            $capability !== null
            && method_exists(
                $capability,
                'discussionForPublication',
            )
        ) {
            $discussion = $capability->discussionForPublication(
                $publication,
            );
            $commentsAvailable =
                ($discussion['available'] ?? false) === true;
            $commentsOpen =
                ($discussion['open'] ?? false) === true;
            $comments = is_array(
                $discussion['comments'] ?? null,
            )
                ? $discussion['comments']
                : [];
        }

        $commentState = trim((string) $request->get('comment', ''));
        $commentFlash = match ($commentState) {
            'queued' => [
                'type' => 'success',
                'message' => 'Комментарий отправлен и появится после проверки.',
            ],
            'published' => [
                'type' => 'success',
                'message' => 'Комментарий опубликован.',
            ],
            'invalid' => [
                'type' => 'error',
                'message' => 'Не удалось отправить комментарий. Проверьте поля и повторите.',
            ],
            default => null,
        };

        $seo = [
            'title' => $publication->title,
            'description' => $publication->excerpt,
            'canonical' => SeoRenderer::absoluteUrl(
                '/publications/' . rawurlencode($publication->slug)
            ),
            'og_type' => 'article',
            'index' => true,
            'follow' => true,
            'published_time' => $publication->publishedAt?->format(DATE_ATOM),
            'modified_time' => $publication->updatedAt->format(DATE_ATOM),
            'author' => $publication->authorName ?? '',
            'section' => $publication->type->value,
        ];

        $seoCapability = ModuleRuntimeLoader::capability('seo', 'seo.publications');
        if ($seoCapability !== null && method_exists($seoCapability, 'metaForPublication')) {
            $resolved = $seoCapability->metaForPublication($publication);
            if (is_array($resolved)) {
                $seo = array_replace($seo, $resolved);
            }
        }

        ThemeRenderer::fromConfig()->page('publication.show', [
            'title' => (string) ($seo['title'] ?? $publication->title),
            'publication' => $publication,
            'categories' => $taxonomy['categories'],
            'tags' => $taxonomy['tags'],
            'commentsAvailable' => $commentsAvailable,
            'commentsOpen' => $commentsOpen,
            'comments' => $comments,
            'commentFlash' => $commentFlash,
            'commentsMaxLength' => (int) Config::get('comments.max_length', 4000),
            'seo' => $seo,
        ], 'layout.article');
    }
}
