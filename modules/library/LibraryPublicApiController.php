<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Library;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class LibraryPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            ['items' => LibraryCatalogService::fromDatabase()->index()],
            cacheSeconds: 60,
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $item = LibraryCatalogService::fromDatabase()->detail($publicId);
        if ($item === null) {
            ApiResponse::error('library_item_not_found', 'Издание не найдено.', 404);
        }

        ApiResponse::success($item, cacheSeconds: 60);
    }
}
