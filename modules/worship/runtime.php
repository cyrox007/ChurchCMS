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
use ChurchCMS\Modules\Worship\WorshipAdminController;
use ChurchCMS\Modules\Worship\WorshipPublicApiController;
use ChurchCMS\Modules\Worship\WorshipPublicController;

$moduleRoot = __DIR__;
foreach ([
    'WorshipService.php',
    'WorshipRepository.php',
    'WorshipApiResource.php',
    'FederatedWorshipFeedService.php',
    'WorshipPartnerTombstoneRepository.php',
    'WorshipPartnerTombstoneApiResource.php',
    'WorshipScheduleService.php',
    'WorshipOrganizationAccessService.php',
    'WorshipRecurrenceRule.php',
    'WorshipRecurrenceRepository.php',
    'WorshipRecurrenceService.php',
    'WorshipCatalogService.php',
    'WorshipAdminController.php',
    'WorshipPublicController.php',
    'WorshipPublicApiController.php',
    'WorshipApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'worship';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'worship',
            label: 'Богослужения',
            route: 'admin_worship',
            permission: 'worship.read',
            priority: 39,
        );

        $router = Router::getInstance();

        $router->add(
            'GET',
            '/admin/worship',
            [WorshipAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_worship',
        );
        $router->add(
            'POST',
            '/admin/worship',
            [WorshipAdminController::class, 'create'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_worship_create',
        );
        $router->add(
            'POST',
            '/admin/worship/{publicId}',
            [WorshipAdminController::class, 'update'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_worship_update',
        );
        $router->add(
            'POST',
            '/admin/worship/{publicId}/cancel',
            [WorshipAdminController::class, 'cancel'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_worship_cancel',
        );
        $router->add(
            'POST',
            '/admin/worship/{publicId}/schedule',
            [WorshipAdminController::class, 'schedule'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_worship_schedule',
        );

        $router->add(
            'GET',
            '/worship',
            [WorshipPublicController::class, 'index'],
            [],
            'worship_index',
        );
        $router->add(
            'GET',
            '/worship/{publicId}',
            [WorshipPublicController::class, 'show'],
            [],
            'worship_show',
        );
        $router->add(
            'GET',
            '/api/v1/worship',
            [WorshipPublicApiController::class, 'index'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_worship',
        );
        $router->add(
            'GET',
            '/api/v1/worship/{publicId}',
            [WorshipPublicApiController::class, 'show'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_worship_show',
        );

        $router->add(
            'GET',
            '/api/v1/worship/aggregated',
            [WorshipApiController::class, 'aggregated'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_worship_aggregated',
        );

        $middlewares = [
            ApiEnabledMiddleware::class,
            ApiCorsMiddleware::class,
            PartnerApiMiddleware::class,
            ApiPartnerRateLimitMiddleware::class,
        ];

        $router->add(
            'GET',
            '/api/v1/partner/worship',
            [WorshipApiController::class, 'partnerIndex'],
            $middlewares,
            'api_v1_partner_worship',
        );

        $router->add(
            'GET',
            '/api/v1/partner/worship/tombstones',
            [WorshipApiController::class, 'partnerTombstones'],
            $middlewares,
            'api_v1_partner_worship_tombstones',
        );
    }
};
