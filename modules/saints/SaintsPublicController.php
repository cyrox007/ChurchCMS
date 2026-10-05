<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Saints;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class SaintsPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'saints.index',
                ['saints' => SaintCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $saint = SaintCatalogService::fromDatabase()->detail($publicId);
        if ($saint === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'saints.show',
                ['saint' => $saint],
            ),
        );
    }
}
