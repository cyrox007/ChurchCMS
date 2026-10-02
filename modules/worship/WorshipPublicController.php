<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class WorshipPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'worship.index',
                [
                    'services' => WorshipCatalogService::fromDatabase()
                        ->upcoming(),
                ],
            ),
        );
    }

    public function show(
        Request $request,
        string $publicId,
    ): never {
        $service = WorshipCatalogService::fromDatabase()
            ->detail($publicId);

        if ($service === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'worship.show',
                [
                    'service' => $service,
                ],
            ),
        );
    }
}
