<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Events;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class EventsPublicController
{
    public function index(Request $request): never
    {
        $catalog = EventCatalogService::fromDatabase();

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'events.index',
                [
                    'events' => $catalog->upcoming(),
                    'calendar' => $catalog->month(
                        (string) $request->get(
                            'month',
                            gmdate('Y-m'),
                        ),
                    ),
                ],
            ),
        );
    }

    public function show(
        Request $request,
        string $publicId,
    ): never {
        $event = EventCatalogService::fromDatabase()
            ->detail($publicId);

        if ($event === null) {
            Response::text('404 Not Found', 404);
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'events.show',
                [
                    'event' => $event,
                ],
            ),
        );
    }
}
