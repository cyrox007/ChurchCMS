<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationSchedules;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class EducationSchedulesPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_schedules.index',
                ['schedules' => EducationScheduleCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $schedule = EducationScheduleCatalogService::fromDatabase()->detail($publicId);
        if ($schedule === null) {
            Response::text('404 Not Found', 404);
        }
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_schedules.show',
                ['schedule' => $schedule],
            ),
        );
    }
}
