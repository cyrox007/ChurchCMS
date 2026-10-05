<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationPrograms;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class EducationProgramsPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_programs.index',
                ['programs' => EducationProgramCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $program = EducationProgramCatalogService::fromDatabase()->detail($publicId);
        if ($program === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_programs.show',
                ['program' => $program],
            ),
        );
    }
}
