<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

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
}
