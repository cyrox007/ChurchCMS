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
    'WorshipService.php',
    'WorshipRepository.php',
    'WorshipApiResource.php',
    'WorshipPartnerTombstoneRepository.php',
    'WorshipPartnerTombstoneApiResource.php',
    'WorshipScheduleService.php',
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
        $router = Router::getInstance();

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
