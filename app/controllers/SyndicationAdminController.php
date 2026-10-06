<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\SyndicationExportLogRepository;

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
}
