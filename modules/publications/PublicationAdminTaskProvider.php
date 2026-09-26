<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\App\Services\AdminTaskProvider;

final class PublicationAdminTaskProvider implements AdminTaskProvider
{
    public function id(): string
    {
        return 'publications';
    }

    public function permission(): string
    {
        return 'publications.read';
    }

    public function tasks(int $limit): array
    {
        $count = PublicationRepository::fromDatabase()
            ->countEditorialWork('default');

        if ($count < 1) {
            return [];
        }

        return [[
            'id' => 'editorial-work',
            'title' => 'Редакционная работа',
            'description' => 'Черновики и материалы на проверке требуют внимания.',
            'count' => $count,
            'severity' => 'info',
            'route' => 'admin_publications',
            'route_params' => [],
        ]];
    }
}
