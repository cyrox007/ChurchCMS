<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Saints;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class SaintsPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => SaintCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $saint = SaintCatalogService::fromDatabase()->detail($publicId);
        if ($saint === null) {
            ApiResponse::error('saint_not_found', 'Святой не найден.', 404);
        }

        ApiResponse::success($saint, cacheSeconds: 60);
    }
}
