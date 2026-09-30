<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class DocumentsPublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            [
                'items' => DocumentCatalogService::fromDatabase()
                    ->index(),
            ],
            cacheSeconds: 60,
        );
    }

    public function show(
        Request $request,
        string $publicId,
    ): never {
        $document = DocumentCatalogService::fromDatabase()
            ->detail($publicId);

        if ($document === null) {
            ApiResponse::error(
                'document_not_found',
                'Документ не найден.',
                404,
            );
        }

        ApiResponse::success(
            $document,
            cacheSeconds: 60,
        );
    }
}
