<?php

declare(strict_types=1);

use ChurchCMS\App\Controllers\HealthController;
use ChurchCMS\App\Controllers\ThemeAssetController;
use ChurchCMS\App\Controllers\Api\V1\ExternalApiController;
use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\Core\Router;

$router = Router::getInstance();

$router->add('GET', '/', [HealthController::class, 'index'], [], 'home');
$router->add('GET', '/health', [HealthController::class, 'health'], [], 'health');
$router->add('GET', '/_theme-asset', [ThemeAssetController::class, 'asset'], [], 'theme_asset');


$router->group('/api/v1')
    ->add('GET', '/meta', [ExternalApiController::class, 'meta'], [ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_meta')
    ->add('OPTIONS', '/meta', [ExternalApiController::class, 'preflight'], [ApiCorsMiddleware::class], 'api_v1_meta_options')
    ->add('GET', '/partner/ping', [ExternalApiController::class, 'partnerPing'], [ApiCorsMiddleware::class, PartnerApiMiddleware::class, ApiPartnerRateLimitMiddleware::class], 'api_v1_partner_ping')
    ->add('OPTIONS', '/partner/ping', [ExternalApiController::class, 'preflight'], [ApiCorsMiddleware::class], 'api_v1_partner_ping_options')
    ->endGroup();
