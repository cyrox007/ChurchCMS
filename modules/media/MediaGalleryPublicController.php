<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\SeoRenderer;
use ChurchCMS\Core\ThemeRenderer;

final class MediaGalleryPublicController
{
    public function index(Request $request): never
    {
        $galleries = MediaGalleryPublicService::fromConfig()
            ->index(
                'default',
                24,
            );
        $siteName = (string) Config::get(
            'site.name',
            Config::get('app.name', 'ChurchCMS'),
        );

        $seo = [
            'title' => 'Галереи — ' . $siteName,
            'description' => 'Фотогалереи ' . $siteName . '.',
            'canonical' => SeoRenderer::absoluteUrl(
                '/galleries'
            ),
            'og_type' => 'website',
            'index' => true,
            'follow' => true,
        ];

        ThemeRenderer::fromConfig()->page(
            'gallery.index',
            [
                'title' => $seo['title'],
                'heading' => 'Галереи',
                'galleries' => $galleries,
                'seo' => $seo,
            ],
        );
    }

    public function show(
        Request $request,
        string $publicId,
    ): never {
        $gallery = MediaGalleryPublicService::fromConfig()
            ->detail(
                $publicId,
                'default',
            );

        if ($gallery === null) {
            Response::text('404 Not Found', 404);
        }

        $seo = [
            'title' => (string) $gallery['title'],
            'description' => (string) $gallery['description'],
            'canonical' => SeoRenderer::absoluteUrl(
                '/galleries/'
                . rawurlencode((string) $gallery['id'])
            ),
            'og_type' => 'website',
            'index' => true,
            'follow' => true,
        ];

        $first = $gallery['items'][0] ?? null;
        if (
            is_array($first)
            && is_string($first['display_url'] ?? null)
            && $first['display_url'] !== ''
        ) {
            $seo['image'] = SeoRenderer::absoluteUrl(
                (string) $first['display_url']
            );
        }

        ThemeRenderer::fromConfig()->page(
            'gallery.show',
            [
                'title' => $gallery['title'],
                'gallery' => $gallery,
                'seo' => $seo,
            ],
        );
    }
}
