<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;
use Throwable;

final class ThemeRenderer
{
    private ThemeManifest $activeTheme;
    private ThemeContext $context;

    public function __construct(
        private readonly ThemeRegistry $registry,
        string $activeThemeId,
    ) {
        $theme = $registry->get($activeThemeId);
        if (!$theme instanceof ThemeManifest) {
            throw new RuntimeException("Active theme not found: {$activeThemeId}");
        }

        $this->activeTheme = $theme;
        $this->context = new ThemeContext($this, $activeThemeId);
    }

    public static function fromConfig(): self
    {
        $active = (string) Config::get('theme.active', 'default');
        return self::forTheme($active);
    }

    public static function forTheme(string $themeId): self
    {
        $root = defined('CHURCHCMS_ROOT') ? CHURCHCMS_ROOT : dirname(__DIR__);
        $registry = ThemeRegistry::boot($root . '/themes');

        return new self($registry, $themeId);
    }

    public function render(string $logicalName, array $data = []): never
    {
        echo $this->capture($logicalName, $data);
        exit;
    }

    public function page(
        string $logicalName,
        array $data = [],
        string $layout = 'layout.main',
    ): never {
        $content = $this->capture($logicalName, $data);
        $layoutData = $data;
        $layoutData['content'] = $content;

        echo $this->capture($layout, $layoutData);
        exit;
    }

    public function capture(string $logicalName, array $data = []): string
    {
        $file = $this->resolveLogicalTemplate($logicalName);
        $theme = $this->context;

        ob_start();
        try {
            extract($data, EXTR_SKIP);
            require $file;
            $output = ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        if ($output === false) {
            throw new RuntimeException("Unable to render template {$logicalName}.");
        }

        return $output;
    }

    public function assetUrl(string $path): string
    {
        if (!$this->isSafeAssetPath($path)) {
            throw new RuntimeException('Invalid theme asset path.');
        }

        return '/_theme-asset?theme=' . rawurlencode($this->activeTheme->id())
            . '&file=' . rawurlencode($path)
            . '&v=' . rawurlencode($this->activeTheme->version());
    }

    public function resolveAsset(string $themeId, string $path): string
    {
        if (!$this->isSafeAssetPath($path)) {
            throw new RuntimeException('Invalid theme asset path.');
        }

        foreach ($this->registry->inheritanceChain($themeId) as $theme) {
            $candidate = $theme->root() . '/assets/' . $path;
            $resolved = realpath($candidate);
            $assetsRoot = realpath($theme->root() . '/assets');

            if ($resolved === false || $assetsRoot === false || !is_file($resolved) || is_link($candidate)) {
                continue;
            }

            $prefix = rtrim($assetsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!str_starts_with($resolved, $prefix)) {
                throw new RuntimeException('Theme asset escaped assets root.');
            }

            return $resolved;
        }

        throw new RuntimeException('Theme asset not found.');
    }

    public function activeTheme(): ThemeManifest
    {
        return $this->activeTheme;
    }

    private function resolveLogicalTemplate(string $logicalName): string
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{1,127}$/D', $logicalName) !== 1) {
            throw new RuntimeException('Invalid logical template name.');
        }

        foreach ($this->registry->inheritanceChain($this->activeTheme->id()) as $theme) {
            $relative = $theme->template($logicalName);
            if ($relative === null) {
                continue;
            }

            $candidate = $theme->root() . '/templates/' . $relative . '.php';
            $resolved = realpath($candidate);
            $templatesRoot = realpath($theme->root() . '/templates');

            if ($resolved === false || $templatesRoot === false || !is_file($resolved) || is_link($candidate)) {
                throw new RuntimeException("Theme template mapping is missing: {$logicalName}");
            }

            $prefix = rtrim($templatesRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!str_starts_with($resolved, $prefix)) {
                throw new RuntimeException('Theme template escaped templates root.');
            }

            return $resolved;
        }

        throw new RuntimeException("Theme template is not defined: {$logicalName}");
    }

    private function isSafeAssetPath(string $path): bool
    {
        return $path !== ''
            && !str_starts_with($path, '/')
            && !str_contains($path, '..')
            && !str_contains($path, '\\')
            && preg_match('/^[A-Za-z0-9_\/.\-]+$/D', $path) === 1;
    }
}
