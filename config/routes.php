<?php

declare(strict_types=1);

use ChurchCMS\App\Controllers\HealthController;
use ChurchCMS\App\Controllers\AdminAuthController;
use ChurchCMS\App\Controllers\AdminController;
use ChurchCMS\App\Controllers\AdminAccountController;
use ChurchCMS\App\Controllers\ThemeAssetController;
use ChurchCMS\App\Controllers\Api\V1\ExternalApiController;
use ChurchCMS\App\Controllers\SyndicationController;
use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\App\Middlewares\AuthRateLimitMiddleware;
use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Middlewares\SecurityHeadersMiddleware;
use ChurchCMS\App\Middlewares\PublicPageCacheMiddleware;
use ChurchCMS\Core\Router;

$router = Router::getInstance();
$router->addGlobalMiddleware(SecurityHeadersMiddleware::class);
$router->addGlobalMiddleware(PublicPageCacheMiddleware::class);

$router->add('GET', '/', [HealthController::class, 'index'], [], 'home');
$router->add('GET', '/health', [HealthController::class, 'health'], [], 'health');
$router->add('GET', '/_theme-asset', [ThemeAssetController::class, 'asset'], [], 'theme_asset');
$router->add('GET', '/feeds/rss.xml', [SyndicationController::class, 'rss'], [], 'feed_rss');
$router->add('GET', '/feeds/rambler.xml', [SyndicationController::class, 'rambler'], [], 'feed_rambler');


$router->group('/api/v1')
    ->add('GET', '/meta', [ExternalApiController::class, 'meta'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_meta')
    ->add('OPTIONS', '/meta', [ExternalApiController::class, 'preflight'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class], 'api_v1_meta_options')
    ->add('GET', '/partner/ping', [ExternalApiController::class, 'partnerPing'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, PartnerApiMiddleware::class, ApiPartnerRateLimitMiddleware::class], 'api_v1_partner_ping')
    ->add('OPTIONS', '/partner/ping', [ExternalApiController::class, 'preflight'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class], 'api_v1_partner_ping_options')
    ->endGroup();


$router->group('/admin')
    ->add('GET', '/login', [AdminAuthController::class, 'login'], [], 'admin_login')
    ->add('POST', '/login', [AdminAuthController::class, 'authenticate'], [AuthRateLimitMiddleware::class, CsrfMiddleware::class], 'admin_login_submit')
    ->add('POST', '/logout', [AdminAuthController::class, 'logout'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_logout')
    ->add('GET', '', [AdminController::class, 'dashboard'], [RequireAdminMiddleware::class], 'admin_dashboard')
    ->add('GET', '/search', [AdminController::class, 'search'], [RequireAdminMiddleware::class], 'admin_search')
    ->add('GET', '/tasks', [AdminController::class, 'tasks'], [RequireAdminMiddleware::class], 'admin_tasks')
    ->add('GET', '/account/password', [AdminAccountController::class, 'password'], [RequireAdminMiddleware::class], 'admin_account_password')
    ->add('POST', '/account/password', [AdminAccountController::class, 'updatePassword'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_account_password_update')
    ->endGroup();
