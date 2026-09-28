<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\App\Services\AdminSearchProvider;

final class PublicationAdminSearchProvider implements AdminSearchProvider
{
    public function id(): string
    {
        return 'publications';
    }

    public function label(): string
    {
        return 'Публикации';
    }

    public function permission(): string
    {
        return 'publications.read';
    }

    public function search(
        string $query,
        int $limit,
        int $userId = 0,
    ): array {
        $results = [];
        $ownerPublicIds =
            PublicationOrganizationAccessService::fromDatabase()
                ->visibleOwnerPublicIds(
                    $userId,
                    'publications.read',
                );

        foreach (
            PublicationRepository::fromDatabase()->adminSearch(
                $query,
                'default',
                $limit,
                $ownerPublicIds,
            ) as $publication
        ) {
            $results[] = [
                'title' => $publication->title,
                'description' => self::description($publication),
                'route' => 'admin_publication_edit',
                'route_params' => [
                    'publicId' => $publication->publicId,
                ],
            ];
        }

        return $results;
    }

    private static function description(Publication $publication): string
    {
        $status = match ($publication->status) {
            PublicationStatus::Draft => 'Черновик',
            PublicationStatus::Review => 'На проверке',
            PublicationStatus::Scheduled => 'Запланировано',
            PublicationStatus::Published => 'Опубликовано',
            PublicationStatus::Withdrawn => 'Снято с публикации',
        };

        $excerpt = trim($publication->excerpt);

        return $excerpt === ''
            ? $status
            : $status . ' · ' . $excerpt;
    }
}
