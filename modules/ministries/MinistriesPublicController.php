<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Ministries;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class MinistriesPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'ministries.index',
                ['ministries' => MinistryCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $ministry = MinistryCatalogService::fromDatabase()->detail($publicId);
        if ($ministry === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'ministries.show',
                ['ministry' => $ministry],
            ),
        );
    }
}
