<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Seo\SeoCapability;
use ChurchCMS\Modules\Seo\SeoController;

$moduleRoot = __DIR__;
foreach ([
    'PublicationSeoRepository.php',
    'SeoCapability.php',
    'SeoController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    private SeoCapability $capability;

    public function moduleId(): string
    {
        return 'seo';
    }

    public function capabilities(): array
    {
        return [
            'seo.publications' => $this->capability,
            'seo.sitemap' => $this->capability,
        ];
    }

    public function boot(): void
    {
        $this->capability = new SeoCapability();

        $router = Router::getInstance();
        $router->add('GET', '/robots.txt', [SeoController::class, 'robots'], [], 'seo_robots');
        $router->add('GET', '/sitemap.xml', [SeoController::class, 'sitemap'], [], 'seo_sitemap');
    }
};
