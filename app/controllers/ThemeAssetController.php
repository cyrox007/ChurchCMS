<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;
use RuntimeException;

final class ThemeAssetController
{
    private const MIME_TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'woff2' => 'font/woff2',
    ];

    public function asset(Request $request): never
    {
        $themeId = (string) $request->get('theme', '');
        $file = (string) $request->get('file', '');

        if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $themeId) !== 1) {
            Response::text('404 Not Found', 404);
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!isset(self::MIME_TYPES[$extension])) {
            Response::text('404 Not Found', 404);
        }

        try {
            $renderer = ThemeRenderer::fromConfig();
            $path = $renderer->resolveAsset($themeId, $file);
        } catch (RuntimeException) {
            Response::text('404 Not Found', 404);
        }

        http_response_code(200);
        header('Content-Type: ' . self::MIME_TYPES[$extension]);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Content-Type-Options: nosniff');

        readfile($path);
        exit;
    }
}
