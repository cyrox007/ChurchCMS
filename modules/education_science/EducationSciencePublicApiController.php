<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationScience;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class EducationSciencePublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => EducationScienceCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $activity = EducationScienceCatalogService::fromDatabase()->detail($publicId);
        if ($activity === null) {
            ApiResponse::error('education_science_not_found', 'Запись научной деятельности не найдена.', 404);
        }
        ApiResponse::success($activity, cacheSeconds: 60);
    }
}
