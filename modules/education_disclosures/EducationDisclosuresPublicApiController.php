<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationDisclosures;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class EducationDisclosuresPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => EducationDisclosureCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $disclosure = EducationDisclosureCatalogService::fromDatabase()->detail($publicId);
        if ($disclosure === null) {
            ApiResponse::error('education_disclosure_not_found', 'Раздел обязательных сведений не найден.', 404);
        }
        ApiResponse::success($disclosure, cacheSeconds: 60);
    }
}
