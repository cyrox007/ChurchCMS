<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Media\MediaApiController;

$moduleRoot = __DIR__;
foreach ([
    'MediaAsset.php',
    'MediaStoredBlob.php',
    'MediaBlobStorage.php',
    'MediaRepository.php',
    'MediaApiResource.php',
    'FederatedMediaFeedService.php',
    'MediaPartnerTombstoneRepository.php',
    'MediaPartnerTombstoneApiResource.php',
    'MediaService.php',
    'MediaUploadService.php',
    'MediaApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'media';
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
            '/api/v1/media/aggregated',
            [MediaApiController::class, 'aggregated'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_media_aggregated',
        );

        $router->add(
            'GET',
            '/api/v1/partner/media',
            [MediaApiController::class, 'partnerIndex'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                PartnerApiMiddleware::class,
                ApiPartnerRateLimitMiddleware::class,
            ],
            'api_v1_partner_media',
        );

        $router->add(
            'GET',
            '/api/v1/partner/media/tombstones',
            [MediaApiController::class, 'partnerTombstones'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                PartnerApiMiddleware::class,
                ApiPartnerRateLimitMiddleware::class,
            ],
            'api_v1_partner_media_tombstones',
        );
    }
};
