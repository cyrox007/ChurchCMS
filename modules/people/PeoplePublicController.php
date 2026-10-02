<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class PeoplePublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'people.index',
                [
                    'people' => PeopleCatalogService::fromDatabase()
                        ->index(),
                ],
            ),
        );
    }

    public function show(
        Request $request,
        string $publicId,
    ): never {
        $person = PeopleCatalogService::fromDatabase()
            ->detail($publicId);

        if ($person === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'people.show',
                [
                    'person' => $person,
                ],
            ),
        );
    }
}
