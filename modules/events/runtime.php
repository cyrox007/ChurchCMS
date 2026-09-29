<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;

$moduleRoot = __DIR__;
foreach ([
    'Event.php',
    'EventRepository.php',
    'EventApiResource.php',
    'EventPartnerTombstoneRepository.php',
    'EventPartnerTombstoneApiResource.php',
    'EventService.php',
    'EventsApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'events';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        $router = Router::getInstance();

        $router->add(
            'GET',
            '/api/v1/partner/events',
            [EventsApiController::class, 'partnerIndex'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                PartnerApiMiddleware::class,
                ApiPartnerRateLimitMiddleware::class,
            ],
            'api_v1_partner_events',
        );

        $router->add(
            'GET',
            '/api/v1/partner/events/tombstones',
            [EventsApiController::class, 'partnerTombstones'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                PartnerApiMiddleware::class,
                ApiPartnerRateLimitMiddleware::class,
            ],
            'api_v1_partner_event_tombstones',
        );
    }
};
