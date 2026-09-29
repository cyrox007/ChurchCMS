<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\InstallationHealthCheck;
use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class HealthController
{
    public function index(Request $request): never
    {
        $siteName = (string) Config::get(
            'site.name',
            Config::get('app.name', 'ChurchCMS'),
        );
        $latestPublications = [];
        $publications = ModuleRuntimeLoader::capability(
            'publications',
            'publications.read',
        );

        if (
            $publications !== null
            && method_exists(
                $publications,
                'latestForPublicTheme',
            )
        ) {
            try {
                $latestPublications = $publications
                    ->latestForPublicTheme('default', 6);
            } catch (\Throwable $error) {
                error_log(
                    'ChurchCMS главная страница: не удалось получить '
                    . 'агрегированную ленту публикаций: '
                    . $error->getMessage(),
                );
            }
        }

        ThemeRenderer::fromConfig()->page('page.home', [
            'title' => $siteName,
            'siteName' => $siteName,
            'heading' => $siteName,
            'lead' => 'Новости и материалы организации и связанных нижестоящих ChurchCMS-сайтов.',
            'latestPublications' => $latestPublications,
        ]);
    }

    public function health(Request $request): never
    {
        $health = (new InstallationHealthCheck(
            DatabaseManager::getInstance(),
            CHURCHCMS_ROOT,
        ))->check();

        Response::json([
            'status' => $health['ready'] ? 'ok' : 'not_ready',
            'version' => Config::get('app.version'),
            'php_min' => Config::get('app.php_min'),
            'theme' => Config::get('theme.active'),
            'checks' => $health['checks'],
        ], $health['ready'] ? 200 : 503);
    }
}
