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
        $navigation = self::navigation($request);
        $badges = [];

        if (isset($navigation['comments'])) {
            $pendingComments = self::pendingComments();
            if ($pendingComments > 0) {
                $badges['comments'] = $pendingComments;
            }
        }

        $shared = [
            'siteName' => 'ChurchCMS',
            'adminUser' => $request->attribute('admin.user'),
            'adminSection' => $section,
            'adminNavigation' => array_values($navigation),
            'adminNavigationBadges' => $badges,
            'adminSearchQuery' => AdminSearchService::query($request),
        ];

        ThemeRenderer::fromConfig()->page(
            $template,
            array_replace($shared, $data),
            'layout.admin',
        );
    }

    /**
     * @return array<string,array{
     *     id:string,
     *     label:string,
     *     route:string,
     *     permission:string,
     *     priority:int
     * }>
     */
    private static function navigation(Request $request): array
    {
        $visible = [];

        foreach (AdminNavigationRegistry::entries() as $entry) {
            if (!AdminAuthorization::can($request, $entry['permission'])) {
                continue;
            }

            $visible[$entry['id']] = $entry;
        }

        return $visible;
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
