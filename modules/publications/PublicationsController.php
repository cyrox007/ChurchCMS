<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class PublicationsController
{
    public function index(Request $request): never
    {
        $repository = PublicationRepository::fromDatabase();
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        ThemeRenderer::fromConfig()->page('publication.index', [
            'title' => 'Публикации',
            'heading' => 'Публикации',
            'publications' => $repository->published('default', $perPage, $offset),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $repository->countPublished('default'),
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

        ThemeRenderer::fromConfig()->page('publication.show', [
            'title' => $publication->title,
            'publication' => $publication,
        ], 'layout.article');
    }
}
