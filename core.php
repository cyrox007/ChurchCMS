<?php

declare(strict_types=1);

define('CHURCHCMS_ROOT', __DIR__);

$autoloader = CHURCHCMS_ROOT . '/core/RuntimeAutoloader.php';
if (!is_file($autoloader)) {
    throw new RuntimeException('ChurchCMS runtime autoloader is missing.');
}
require_once $autoloader;

\ChurchCMS\Core\RuntimeAutoloader::register(CHURCHCMS_ROOT);

$required = [
    '/core/Config.php',
    '/core/SessionSecurity.php',
    '/core/Csrf.php',
    '/core/SecurityHeaders.php',
    '/core/SecretVault.php',
    '/core/PageCache.php',
    '/core/PublicFormToken.php',
    '/core/SeoRenderer.php',
    '/core/ShareLinks.php',
    '/core/Request.php',
    '/core/Response.php',
    '/core/RouteTemplate.php',
    '/core/Router.php',
    '/core/DatabaseManager.php',
    '/core/Migration.php',
    '/core/MigrationRunner.php',
    '/core/ApiResponse.php',
    '/core/ApiAccess.php',
    '/core/ApiResource.php',
    '/core/ApiTokenAuthenticator.php',
    '/core/RateLimiter.php',
    '/core/SyndicationEntry.php',
    '/core/SyndicationProvider.php',
    '/core/SyndicationRegistry.php',
    '/core/SyndicationFeed.php',
    '/core/SyndicationRenderer.php',
    '/core/Rss2SyndicationRenderer.php',
    '/core/RamblerSyndicationRenderer.php',
    '/core/ModuleManifest.php',
    '/core/ModuleRegistry.php',
    '/core/ModuleRuntimeProvider.php',
    '/core/ModuleRuntimeLoader.php',
];

foreach ($required as $file) {
    $path = CHURCHCMS_ROOT . $file;
    if (!is_file($path)) {
        throw new RuntimeException("Required core file is missing: {$file}");
    }
    require_once $path;
}

\ChurchCMS\Core\Config::load(CHURCHCMS_ROOT . '/config/app.php');
\ChurchCMS\Core\SessionSecurity::configure();

$registry = \ChurchCMS\Core\ModuleRegistry::boot(CHURCHCMS_ROOT . '/modules');
\ChurchCMS\Core\ModuleRuntimeLoader::boot($registry);

require CHURCHCMS_ROOT . '/config/routes.php';
