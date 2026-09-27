<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Organizations\FederationApiController;

$moduleRoot = __DIR__;
foreach ([
    'OrganizationUnit.php',
    'FederationLink.php',
    'OrganizationRepository.php',
    'FederationRepository.php',
    'OrganizationService.php',
    'FederationService.php',
    'FederationApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'organizations';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        Router::getInstance()->add(
            'GET',
            '/api/v1/federation/meta',
            [FederationApiController::class, 'meta'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_federation_meta',
        );
    }
};
