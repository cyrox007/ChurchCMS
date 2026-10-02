<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class WorshipPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            [
                'items' => WorshipCatalogService::fromDatabase()
                    ->upcoming(),
            ],
            cacheSeconds: 60,
        );
    }

    public function show(
        Request $request,
        string $publicId,
    ): never {
        $service = WorshipCatalogService::fromDatabase()
            ->detail($publicId);

        if ($service === null) {
            ApiResponse::error(
                'worship_not_found',
                'Богослужение не найдено.',
                404,
            );
        }

        ApiResponse::success(
            $service,
            cacheSeconds: 60,
        );
    }
}
