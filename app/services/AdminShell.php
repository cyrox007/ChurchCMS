<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use ChurchCMS\Core\Config;
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
        $profile = (string) Config::get('site.profile', 'parish');
        $navigation = self::navigation($request, $profile);
        $taskSummary = AdminTaskCenter::summary($request);
        $badges = [];

        if (isset($navigation['comments'])) {
            $pendingComments = self::pendingComments();
            if ($pendingComments > 0) {
                $badges['comments'] = $pendingComments;
            }
        }

        $shared = [
            'siteName' => (string) Config::get('site.name', 'ChurchCMS'),
            'siteProfile' => $profile,
            'siteProfileLabel' => AdminNavigationRegistry::profileLabel($profile),
            'adminUser' => $request->attribute('admin.user'),
            'adminSection' => $section,
            'adminNavigation' => array_values($navigation),
            'adminNavigationBadges' => $badges,
            'adminSearchQuery' => AdminSearchService::query($request),
            'adminTaskCount' => $taskSummary['count'],
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
     *     priority:int,
     *     group:string
     * }>
     */
    private static function navigation(
        Request $request,
        string $profile,
    ): array {
        $visible = [];

        foreach (
            AdminNavigationRegistry::entriesForProfile($profile)
            as $entry
        ) {
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
