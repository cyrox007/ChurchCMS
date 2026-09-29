<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\HtmlSanitizer;
use InvalidArgumentException;

final class PublicationsCapability
{
    public function repository(): PublicationRepository
    {
        return PublicationRepository::fromDatabase();
    }

    public function service(): PublicationService
    {
        return PublicationService::fromDatabase();
    }

    /**
     * @return list<array{public_id:string,name:string,path:string}>
     */
    public function externalImportOwners(
        int $userId,
        string $siteKey = 'default',
    ): array {
        return array_map(
            static fn($owner): array => [
                'public_id' => $owner->publicId,
                'name' => $owner->name,
                'path' => $owner->path,
            ],
            PublicationOrganizationAccessService::fromDatabase()
                ->availableOwners(
                    $userId,
                    'publications.create',
                    $siteKey,
                ),
        );
    }

    public function findLinkablePublication(
        int $userId,
        string $publicId,
        string $siteKey = 'default',
    ): ?Publication {
        $publication = $this->repository()->findByPublicId(
            trim($publicId),
            $siteKey,
        );
        if ($publication === null) {
            return null;
        }

        return PublicationOrganizationAccessService::fromDatabase()
            ->canAccess(
                $userId,
                'publications.read',
                $publication,
            )
            ? $publication
            : null;
    }

    /**
     * @return array{id:int,public_id:string}
     */
    public function importExternalDraft(
        int $userId,
        string $ownerPublicId,
        string $title,
        string $bodyText,
        string $kind = 'post',
        ?string $canonicalUrl = null,
        string $siteKey = 'default',
    ): array {
        $owner = PublicationOrganizationAccessService::fromDatabase()
            ->assignableOwner(
                $userId,
                'publications.create',
                $ownerPublicId,
                $siteKey,
            );
        if ($owner === null) {
            throw new InvalidArgumentException(
                'Организация недоступна для создания публикации.'
            );
        }

        $title = trim($title);
        if ($title === '') {
            $title = 'Материал из внешнего канала';
        }

        $canonicalUrl = trim((string) $canonicalUrl);
        $excerpt = $canonicalUrl !== ''
            ? 'Источник: ' . $canonicalUrl
            : '';

        $publicId = $this->service()->createDraft(
            title: $title,
            type: self::externalKindToPublicationType($kind),
            excerpt: $excerpt,
            bodyHtml: HtmlSanitizer::fromPlainText($bodyText),
            ownerOrganizationPublicId: $owner->publicId,
            siteKey: $siteKey,
        );

        $publication = $this->repository()->findByPublicId(
            $publicId,
            $siteKey,
        );
        if ($publication === null) {
            throw new \RuntimeException(
                'Импортированный черновик не удалось перечитать.'
            );
        }

        return [
            'id' => $publication->id,
            'public_id' => $publication->publicId,
        ];
    }

    /**
     * Безопасная общая лента для публичных блоков темы.
     *
     * @return list<array<string,mixed>>
     */
    public function latestForPublicTheme(
        string $siteKey = 'default',
        int $limit = 6,
    ): array {
        return FederatedPublicationFeedService::fromDatabase()
            ->latest(
                $siteKey,
                $limit,
            );
    }

    private static function externalKindToPublicationType(
        string $kind,
    ): PublicationType {
        return match (strtolower(trim($kind))) {
            'article' => PublicationType::Article,
            'announcement' => PublicationType::Announcement,
            'sermon' => PublicationType::Sermon,
            'interview' => PublicationType::Interview,
            'document' => PublicationType::Document,
            default => PublicationType::News,
        };
    }
}
