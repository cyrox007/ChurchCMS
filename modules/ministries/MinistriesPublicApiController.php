<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Ministries;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class MinistriesPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => MinistryCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $ministry = MinistryCatalogService::fromDatabase()->detail($publicId);
        if ($ministry === null) {
            ApiResponse::error('ministry_not_found', 'Служение не найдено.', 404);
        }

        ApiResponse::success($ministry, cacheSeconds: 60);
    }
}
