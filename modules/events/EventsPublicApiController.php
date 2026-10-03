<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Events;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class EventsPublicApiController
{
    public function index(Request $request): never
    {
        $catalog = EventCatalogService::fromDatabase();

        ApiResponse::success(
            [
                'items' => $catalog->upcoming(),
                'calendar' => $catalog->month(
                    (string) $request->get(
                        'month',
                        gmdate('Y-m'),
                    ),
                ),
            ],
            cacheSeconds: 60,
        );
    }

    public function show(
        Request $request,
        string $publicId,
    ): never {
        $event = EventCatalogService::fromDatabase()
            ->detail($publicId);

        if ($event === null) {
            ApiResponse::error(
                'event_not_found',
                'Событие не найдено.',
                404,
            );
        }

        ApiResponse::success(
            $event,
            cacheSeconds: 60,
        );
    }
}
