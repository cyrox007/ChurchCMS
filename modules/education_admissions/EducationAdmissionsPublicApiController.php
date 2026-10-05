<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationAdmissions;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class EducationAdmissionsPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => EducationAdmissionCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $admission = EducationAdmissionCatalogService::fromDatabase()->detail($publicId);
        if ($admission === null) {
            ApiResponse::error('education_admission_not_found', 'Приёмная кампания не найдена.', 404);
        }

        ApiResponse::success($admission, cacheSeconds: 60);
    }
}
