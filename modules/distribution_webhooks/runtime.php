<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\Core\DistributionEventRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\DistributionWebhooks\DistributionWebhookAdminController;
use ChurchCMS\Modules\DistributionWebhooks\DistributionWebhookService;

foreach ([
    'DistributionWebhookEndpoint.php',
    'DistributionWebhookEndpointRepository.php',
    'DistributionWebhookDeliveryRepository.php',
    'DistributionWebhookTransport.php',
    'DistributionWebhookHttpClient.php',
    'DistributionWebhookSignature.php',
    'DistributionWebhookService.php',
    'DistributionWebhookWorker.php',
    'DistributionWebhookAdminController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'distribution_webhooks';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        DistributionEventRegistry::register(
            DistributionWebhookService::fromDatabase(),
        );

        AdminNavigationRegistry::register(
            id: 'distribution_webhooks',
            label: 'Исходящие webhooks',
            route: 'admin_distribution_webhooks',
            permission: 'distribution_webhooks.read',
            priority: 24,
        );

        $router = Router::getInstance();
        $router->add(
            'GET',
            '/admin/distribution-webhooks',
            [DistributionWebhookAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_distribution_webhooks',
        );
        $router->add(
            'POST',
            '/admin/distribution-webhooks',
            [DistributionWebhookAdminController::class, 'create'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_distribution_webhooks_create',
        );
        $router->add(
            'POST',
            '/admin/distribution-webhooks/{publicId}/enable',
            [DistributionWebhookAdminController::class, 'enable'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_distribution_webhooks_enable',
        );
        $router->add(
            'POST',
            '/admin/distribution-webhooks/{publicId}/disable',
            [DistributionWebhookAdminController::class, 'disable'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_distribution_webhooks_disable',
        );
    }
};
