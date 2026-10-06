<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\Config;
use ChurchCMS\Core\RamblerFeedValidator;
use ChurchCMS\Core\RamblerSyndicationRenderer;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\SyndicationExportLogRepository;
use ChurchCMS\Core\SyndicationFeed;
use ChurchCMS\Core\SyndicationRegistry;

final class SyndicationAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.read');

        $target = trim((string) $request->get('target'));
        if (!in_array($target, ['', 'rss', 'rambler'], true)) {
            $target = '';
        }

        $entries = SyndicationExportLogRepository::fromDefaultConnection()
            ->recent(200, $target === '' ? null : $target);

        AdminShell::page($request, 'admin.syndication_exports', [
            'title' => 'Журнал экспортов',
            'entries' => $entries,
            'target' => $target,
        ], 'publications');
    }

    public function ramblerValidator(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.read');

        $siteUrl = rtrim((string) Config::get('syndication.site_url', ''), '/');
        $configured = $siteUrl !== ''
            && filter_var($siteUrl, FILTER_VALIDATE_URL) !== false
            && Config::get('syndication.targets.rambler.enabled', false) === true;

        $issues = [];
        $itemCount = 0;
        if ($configured) {
            $entries = SyndicationRegistry::entriesFor('rambler', 100);
            $itemCount = count($entries);
            $feed = new SyndicationFeed(
                title: (string) Config::get('syndication.channel_title', 'ChurchCMS'),
                siteUrl: $siteUrl . '/',
                description: (string) Config::get('syndication.channel_description', 'ChurchCMS feed'),
                entries: $entries,
            );
            $renderer = new RamblerSyndicationRenderer();
            $xml = $renderer->render($feed);
            $issues = (new RamblerFeedValidator())->validate(
                $xml,
                $renderer->contentType(),
            );
        }

        $errorCount = count(array_filter(
            $issues,
            static fn(array $issue): bool => ($issue['level'] ?? '') === 'error',
        ));
        $warningCount = count($issues) - $errorCount;

        AdminShell::page($request, 'admin.rambler_validator', [
            'title' => 'Проверка Rambler-фида',
            'configured' => $configured,
            'issues' => $issues,
            'itemCount' => $itemCount,
            'errorCount' => $errorCount,
            'warningCount' => $warningCount,
        ], 'publications');
    }
}
