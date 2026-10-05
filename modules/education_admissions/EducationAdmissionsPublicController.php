<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationAdmissions;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class EducationAdmissionsPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_admissions.index',
                ['admissions' => EducationAdmissionCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $admission = EducationAdmissionCatalogService::fromDatabase()->detail($publicId);
        if ($admission === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_admissions.show',
                ['admission' => $admission],
            ),
        );
    }
}
