<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\SundaySchool;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class SundaySchoolsPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => SundaySchoolCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $school = SundaySchoolCatalogService::fromDatabase()->detail($publicId);
        if ($school === null) {
            ApiResponse::error('sunday_school_not_found', 'Воскресная школа не найдена.', 404);
        }

        ApiResponse::success($school, cacheSeconds: 60);
    }
}
