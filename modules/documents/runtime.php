<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Modules\Documents\DocumentsApiController;
use ChurchCMS\Core\Router;

$moduleRoot = __DIR__;
foreach ([
    'DocumentRecord.php',
    'DocumentRepository.php',
    'DocumentApiResource.php',
    'FederatedDocumentFeedService.php',
    'DocumentPartnerTombstoneRepository.php',
    'DocumentPartnerTombstoneApiResource.php',
    'DocumentService.php',
    'DocumentMediaService.php',
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
            '/api/v1/documents/aggregated',
            [DocumentsApiController::class, 'aggregated'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_documents_aggregated',
        );

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
