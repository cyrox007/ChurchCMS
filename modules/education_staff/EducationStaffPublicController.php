<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationStaff;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class EducationStaffPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_staff.index',
                ['chairs' => EducationStaffCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $chair = EducationStaffCatalogService::fromDatabase()->detail($publicId);
        if ($chair === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_staff.show',
                ['chair' => $chair],
            ),
        );
    }
}
