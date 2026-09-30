<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class DocumentsPublicController
{
    public function index(Request $request): never
    {
        $items = DocumentCatalogService::fromDatabase()
            ->index();

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'document.index',
                [
                    'documents' => $items,
                ],
            ),
        );
    }

    public function show(
        Request $request,
        string $publicId,
    ): never {
        $document = DocumentCatalogService::fromDatabase()
            ->detail($publicId);

        if ($document === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'document.show',
                [
                    'document' => $document,
                ],
            ),
        );
    }
}
