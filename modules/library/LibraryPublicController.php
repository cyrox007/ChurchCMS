<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Library;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class LibraryPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'library.index',
                ['items' => LibraryCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $item = LibraryCatalogService::fromDatabase()->detail($publicId);
        if ($item === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'library.show',
                ['item' => $item],
            ),
        );
    }
}
