<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Search\SearchPublicApiController;
use ChurchCMS\Modules\Search\SearchPublicController;

$moduleRoot = __DIR__;
foreach ([
    'PublicSearchService.php',
    'SearchPublicController.php',
    'SearchPublicApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'search';
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
            '/search',
            [SearchPublicController::class, 'index'],
            [],
            'search_index',
        );

        $router->add(
            'GET',
            '/api/v1/search',
            [SearchPublicApiController::class, 'index'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_search',
        );
    }
};
