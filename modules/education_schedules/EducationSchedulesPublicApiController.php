<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationSchedules;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class EducationSchedulesPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => EducationScheduleCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $schedule = EducationScheduleCatalogService::fromDatabase()->detail($publicId);
        if ($schedule === null) {
            ApiResponse::error('education_schedule_not_found', 'Запись расписания не найдена.', 404);
        }
        ApiResponse::success($schedule, cacheSeconds: 60);
    }
}
