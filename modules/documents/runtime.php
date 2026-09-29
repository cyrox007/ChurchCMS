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
    'DocumentRecord.php',
    'DocumentRepository.php',
    'DocumentApiResource.php',
    'DocumentPartnerTombstoneRepository.php',
    'DocumentPartnerTombstoneApiResource.php',
    'DocumentService.php',
    'DocumentsApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'documents';
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
            '/api/v1/partner/documents',
            [DocumentsApiController::class, 'partnerIndex'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                PartnerApiMiddleware::class,
                ApiPartnerRateLimitMiddleware::class,
            ],
            'api_v1_partner_documents',
        );

        $router->add(
            'GET',
            '/api/v1/partner/documents/tombstones',
            [DocumentsApiController::class, 'partnerTombstones'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                PartnerApiMiddleware::class,
                ApiPartnerRateLimitMiddleware::class,
            ],
            'api_v1_partner_document_tombstones',
        );
    }
};
