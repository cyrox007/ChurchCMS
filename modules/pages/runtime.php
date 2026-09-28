<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Pages\PagesApiController;

$moduleRoot = __DIR__;
foreach ([
    'PageStatus.php',
    'Page.php',
    'PageRepository.php',
    'PageService.php',
    'PageApiResource.php',
    'PagesApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'pages';
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
            ApiPublicRateLimitMiddleware::class,
        ];

        $router->add(
            'GET',
            '/api/v1/pages',
            [PagesApiController::class, 'index'],
            $middlewares,
            'api_v1_pages',
        );
        $router->add(
            'GET',
            '/api/v1/pages/{publicId}',
            [PagesApiController::class, 'show'],
            $middlewares,
            'api_v1_page_show',
        );
    }
};
