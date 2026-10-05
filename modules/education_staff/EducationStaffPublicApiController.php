<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationStaff;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class EducationStaffPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => EducationStaffCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $chair = EducationStaffCatalogService::fromDatabase()->detail($publicId);
        if ($chair === null) {
            ApiResponse::error('education_chair_not_found', 'Кафедра не найдена.', 404);
        }

        ApiResponse::success($chair, cacheSeconds: 60);
    }
}
