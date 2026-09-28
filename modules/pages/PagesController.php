<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\SeoRenderer;
use ChurchCMS\Core\ThemeRenderer;

final class PagesController
{
    public function show(Request $request, string $path): never
    {
        $path = trim($path, '/');
        if (
            preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/D',
                $path,
            ) !== 1
        ) {
            Response::text('404 Not Found', 404);
        }

        $repository = PageRepository::fromDatabase();
        $page = $repository->findPublishedByPath('/' . $path);
        if ($page === null) {
            Response::text('404 Not Found', 404);
        }

        $canonicalPath = '/pages' . $page->path;
        $seo = [
            'title' => $page->title,
            'description' => self::description($page->bodyHtml),
            'canonical' => SeoRenderer::absoluteUrl($canonicalPath),
            'og_type' => 'website',
            'index' => true,
            'follow' => true,
            'published_time' => $page->publishedAt?->format(
                DATE_ATOM,
            ),
            'modified_time' => $page->updatedAt->format(DATE_ATOM),
        ];

        ThemeRenderer::fromConfig()->page('page.show', [
            'title' => $page->title,
            'page' => $page,
            'breadcrumbs' => self::breadcrumbs(
                $repository,
                $page,
            ),
            'canonicalPath' => $canonicalPath,
            'seo' => $seo,
        ], 'layout.article');
    }

    /**
     * @return list<array{title:string,path:string,current:bool}>
     */
    private static function breadcrumbs(
        PageRepository $repository,
        Page $page,
    ): array {
        $segments = explode('/', trim($page->path, '/'));
        $path = '';
        $items = [];

        foreach ($segments as $index => $segment) {
            $path .= '/' . $segment;
            $ancestor = $repository->findPublishedByPath(
                $path,
                $page->siteKey,
            );

            if ($ancestor === null) {
                continue;
            }

            $items[] = [
                'title' => $ancestor->navigationTitle
                    ?? $ancestor->title,
                'path' => '/pages' . $ancestor->path,
                'current' => $index === array_key_last($segments),
            ];
        }

        return $items;
    }

    private static function description(string $html): string
    {
        $text = trim(preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(
                strip_tags($html),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ),
        ) ?? '');

        return function_exists('mb_substr')
            ? mb_substr($text, 0, 180, 'UTF-8')
            : substr($text, 0, 180);
    }
}
