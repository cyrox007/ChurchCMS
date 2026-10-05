<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Shrines;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class ShrinesPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => ShrineCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $shrine = ShrineCatalogService::fromDatabase()->detail($publicId);
        if ($shrine === null) {
            ApiResponse::error('shrine_not_found', 'Святыня не найдена.', 404);
        }

        ApiResponse::success($shrine, cacheSeconds: 60);
    }
}
