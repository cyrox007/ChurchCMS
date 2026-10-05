<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationScience;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class EducationSciencePublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_science.index',
                ['activities' => EducationScienceCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $activity = EducationScienceCatalogService::fromDatabase()->detail($publicId);
        if ($activity === null) {
            Response::text('404 Not Found', 404);
        }
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_science.show',
                ['activity' => $activity],
            ),
        );
    }
}
