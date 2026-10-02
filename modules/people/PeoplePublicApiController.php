<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class PeoplePublicApiController
{
    public function index(Request $request): never
    {
        ApiResponse::success(
            [
                'items' => PeopleCatalogService::fromDatabase()
                    ->index(),
            ],
            cacheSeconds: 60,
        );
    }

    public function show(
        Request $request,
        string $publicId,
    ): never {
        $person = PeopleCatalogService::fromDatabase()
            ->detail($publicId);

        if ($person === null) {
            ApiResponse::error(
                'person_not_found',
                'Карточка человека не найдена.',
                404,
            );
        }

        ApiResponse::success(
            $person,
            cacheSeconds: 60,
        );
    }
}
