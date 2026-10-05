<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\SundaySchool;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class SundaySchoolsPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'sunday_school.index',
                ['schools' => SundaySchoolCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $school = SundaySchoolCatalogService::fromDatabase()->detail($publicId);
        if ($school === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'sunday_school.show',
                ['school' => $school],
            ),
        );
    }
}
