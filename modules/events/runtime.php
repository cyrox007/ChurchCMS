<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Events\EventsAdminController;
use ChurchCMS\Modules\Events\EventsPublicApiController;
use ChurchCMS\Modules\Events\EventsPublicController;

$moduleRoot = __DIR__;
foreach ([
    'Event.php',
    'EventRepository.php',
    'EventApiResource.php',
    'FederatedEventFeedService.php',
    'EventPartnerTombstoneRepository.php',
    'EventPartnerTombstoneApiResource.php',
    'EventService.php',
    'EventOrganizationAccessService.php',
    'EventRecurrenceRule.php',
    'EventRecurrenceRepository.php',
    'EventRecurrenceService.php',
    'EventCatalogService.php',
    'EventsAdminController.php',
    'EventsPublicController.php',
    'EventsPublicApiController.php',
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
        AdminNavigationRegistry::register(
            id: 'events',
            label: 'События',
            route: 'admin_events',
            permission: 'events.read',
            priority: 40,
        );

        $router = Router::getInstance();

        $router->add(
            'GET',
            '/admin/events',
            [EventsAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_events',
        );
        $router->add(
            'POST',
            '/admin/events',
            [EventsAdminController::class, 'create'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_events_create',
        );
        $router->add(
            'POST',
            '/admin/events/{publicId}',
            [EventsAdminController::class, 'update'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_events_update',
        );
        $router->add(
            'POST',
            '/admin/events/{publicId}/publish',
            [EventsAdminController::class, 'publish'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_events_publish',
        );
        $router->add(
            'POST',
            '/admin/events/{publicId}/withdraw',
            [EventsAdminController::class, 'withdraw'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_events_withdraw',
        );
        $router->add(
            'POST',
            '/admin/events/{publicId}/cancel',
            [EventsAdminController::class, 'cancel'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_events_cancel',
        );

        $router->add(
            'GET',
            '/events',
            [EventsPublicController::class, 'index'],
            [],
            'events_index',
        );
        $router->add(
            'GET',
            '/events/{publicId}',
            [EventsPublicController::class, 'show'],
            [],
            'events_show',
        );
        $router->add(
            'GET',
            '/api/v1/events',
            [EventsPublicApiController::class, 'index'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_events',
        );
        $router->add(
            'GET',
            '/api/v1/events/{publicId}',
            [EventsPublicApiController::class, 'show'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_events_show',
        );

        $router->add(
            'GET',
            '/api/v1/events/aggregated',
            [EventsApiController::class, 'aggregated'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_events_aggregated',
        );

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
