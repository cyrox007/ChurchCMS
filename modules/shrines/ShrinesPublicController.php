<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Shrines;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class ShrinesPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'shrines.index',
                ['shrines' => ShrineCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $shrine = ShrineCatalogService::fromDatabase()->detail($publicId);
        if ($shrine === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'shrines.show',
                ['shrine' => $shrine],
            ),
        );
    }
}
