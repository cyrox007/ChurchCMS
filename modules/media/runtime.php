<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Media\MediaAdminController;
use ChurchCMS\Modules\Media\MediaGalleryAdminController;
use ChurchCMS\Modules\Media\MediaApiController;
use ChurchCMS\Modules\Media\MediaPublicController;
use ChurchCMS\Modules\Media\MediaGalleryApiController;
use ChurchCMS\Modules\Media\MediaGalleryPublicController;

$moduleRoot = __DIR__;
foreach ([
    'MediaAsset.php',
    'MediaStoredBlob.php',
    'MediaBlobStorage.php',
    'MediaImageMetadata.php',
    'MediaImageMetadataReader.php',
    'MediaDerivative.php',
    'MediaDerivativeRepository.php',
    'MediaImageDerivativeService.php',
    'MediaDerivativeCapability.php',
    'MediaBinarySource.php',
    'MediaBinaryChunk.php',
    'MediaResumableTransfer.php',
    'MediaBinarySourceService.php',
    'MediaResumableTransferRepository.php',
    'MediaResumableTransferService.php',
    'MediaResumableUploadCapability.php',
    'MediaGallery.php',
    'MediaGalleryRepository.php',
    'MediaGalleryService.php',
    'MediaGalleryCapability.php',
    'MediaGalleryPublicService.php',
    'MediaGalleryApiController.php',
    'MediaGalleryPublicController.php',
    'MediaRepository.php',
    'MediaUsageReference.php',
    'MediaUsageRepository.php',
    'MediaUsageService.php',
    'MediaStructuredSeoService.php',
    'MediaUsageCapability.php',
    'MediaOrganizationAccessService.php',
    'MediaApiResource.php',
    'FederatedMediaFeedService.php',
    'MediaPartnerTombstoneRepository.php',
    'MediaPartnerTombstoneApiResource.php',
    'MediaService.php',
    'MediaUploadService.php',
    'MediaPublicFile.php',
    'MediaPublicFileService.php',
    'MediaPublicController.php',
    'MediaGalleryAdminController.php',
    'MediaAdminController.php',
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
        return [
            'media.usage-references' =>
                new \ChurchCMS\Modules\Media\MediaUsageCapability(),
            'media.derivatives' =>
                new \ChurchCMS\Modules\Media\MediaDerivativeCapability(),
            'media.resumable-upload' =>
                new \ChurchCMS\Modules\Media\MediaResumableUploadCapability(),
            'media.galleries' =>
                new \ChurchCMS\Modules\Media\MediaGalleryCapability(),
        ];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'media',
            label: 'Медиатека',
            route: 'admin_media',
            permission: 'media.manage',
            priority: 35,
        );
        AdminNavigationRegistry::register(
            id: 'media-galleries',
            label: 'Галереи',
            route: 'admin_media_galleries',
            permission: 'media.manage',
            priority: 36,
        );

        $router = Router::getInstance();

        $router->add(
            'GET',
            '/admin/media',
            [MediaAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_media',
        );

        $router->add(
            'POST',
            '/admin/media/upload',
            [MediaAdminController::class, 'upload'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_media_upload',
        );

        $router->add(
            'POST',
            '/admin/media/{publicId}/visibility',
            [MediaAdminController::class, 'visibility'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_media_visibility',
        );

        $router->add(
            'GET',
            '/admin/media/galleries',
            [MediaGalleryAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_media_galleries',
        );

        $router->add(
            'POST',
            '/admin/media/galleries',
            [MediaGalleryAdminController::class, 'create'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_media_galleries_create',
        );

        $router->add(
            'POST',
            '/admin/media/galleries/{publicId}',
            [MediaGalleryAdminController::class, 'update'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_media_galleries_update',
        );

        $router->add(
            'POST',
            '/admin/media/galleries/{publicId}/publish',
            [MediaGalleryAdminController::class, 'publish'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_media_galleries_publish',
        );

        $router->add(
            'POST',
            '/admin/media/galleries/{publicId}/withdraw',
            [MediaGalleryAdminController::class, 'withdraw'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_media_galleries_withdraw',
        );

        $router->add(
            'POST',
            '/admin/media/galleries/{publicId}/archive',
            [MediaGalleryAdminController::class, 'archive'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_media_galleries_archive',
        );

        $router->add(
            'GET',
            '/galleries',
            [MediaGalleryPublicController::class, 'index'],
            [],
            'gallery_index',
        );

        $router->add(
            'GET',
            '/galleries/{publicId}',
            [MediaGalleryPublicController::class, 'show'],
            [],
            'gallery_show',
        );

        $router->add(
            'GET',
            '/api/v1/galleries',
            [MediaGalleryApiController::class, 'index'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_galleries',
        );

        $router->add(
            'GET',
            '/api/v1/galleries/{publicId}',
            [MediaGalleryApiController::class, 'show'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_gallery_show',
        );

        $router->add(
            'GET',
            '/media/{publicId}/{sha256}/{variant}',
            [MediaPublicController::class, 'file'],
            [],
            'public_media_file',
        );

        $router->add(
            'HEAD',
            '/media/{publicId}/{sha256}/{variant}',
            [MediaPublicController::class, 'file'],
            [],
            'public_media_file_head',
        );

        $router->add(
            'GET',
            '/api/v1/media/{publicId}',
            [MediaApiController::class, 'show'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_media_show',
        );

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
