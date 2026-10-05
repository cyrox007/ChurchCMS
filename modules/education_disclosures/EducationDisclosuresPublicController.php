<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationDisclosures;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class EducationDisclosuresPublicController
{
    public function index(Request $request): never
    {
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_disclosures.index',
                ['disclosures' => EducationDisclosureCatalogService::fromDatabase()->index()],
            ),
        );
    }

    public function show(Request $request, string $publicId): never
    {
        $disclosure = EducationDisclosureCatalogService::fromDatabase()->detail($publicId);
        if ($disclosure === null) {
            Response::text('404 Not Found', 404);
        }
        Response::html(
            ThemeRenderer::fromConfig()->render(
                'education_disclosures.show',
                ['disclosure' => $disclosure],
            ),
        );
    }
}
