<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationPrograms;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class EducationProgramsPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => EducationProgramCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $program = EducationProgramCatalogService::fromDatabase()->detail($publicId);
        if ($program === null) {
            ApiResponse::error('education_program_not_found', 'Образовательная программа не найдена.', 404);
        }

        ApiResponse::success($program, cacheSeconds: 60);
    }
}
