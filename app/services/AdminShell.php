<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\ThemeRenderer;

final class AdminShell
{
    /**
     * @param array<string,mixed> $data
     */
    public static function page(
        Request $request,
        string $template,
        array $data = [],
        string $section = 'overview',
    ): never {
        $canManagePublications = AdminAuthorization::can(
            $request,
            'publications.read',
        );
        $canModerateComments = AdminAuthorization::can(
            $request,
            'comments.moderate',
        );

        $pendingComments = $canModerateComments
            ? self::pendingComments()
            : 0;

        $shared = [
            'siteName' => 'ChurchCMS',
            'adminUser' => $request->attribute('admin.user'),
            'adminSection' => $section,
            'canManagePublications' => $canManagePublications,
            'canModerateComments' => $canModerateComments,
            'pendingComments' => $pendingComments,
        ];

        ThemeRenderer::fromConfig()->page(
            $template,
            array_replace($shared, $data),
            'layout.admin',
        );
    }

    private static function pendingComments(): int
    {
        $capability = ModuleRuntimeLoader::capability(
            'comments',
            'comments.moderation',
        );

        if ($capability === null || !method_exists($capability, 'pendingCount')) {
            return 0;
        }

        return max(0, (int) $capability->pendingCount());
    }
}
