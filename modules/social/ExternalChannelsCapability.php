<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class ExternalChannelsCapability
{
    public function connections(): SocialConnectionRepository
    {
        return SocialConnectionRepository::fromDatabase();
    }

    public function inbox(): ExternalChannelItemRepository
    {
        return ExternalChannelItemRepository::fromDatabase();
    }

    public function sync(): ChannelSyncService
    {
        return new ChannelSyncService();
    }

    public function publicationEditorConnections(
        ?int $publicationId,
    ): array {
        return SocialPublicationChannelService::fromDatabase()
            ->editorConnections($publicationId);
    }

    /**
     * @param list<string> $connectionPublicIds
     * @return list<string>
     */
    public function validatePublicationSelection(
        array $connectionPublicIds,
    ): array {
        return SocialPublicationChannelService::fromDatabase()
            ->validateSelection($connectionPublicIds);
    }

    /**
     * @param list<string> $connectionPublicIds
     */
    public function savePublicationSelection(
        int $publicationId,
        array $connectionPublicIds,
    ): void {
        SocialPublicationChannelService::fromDatabase()
            ->saveSelection(
                $publicationId,
                $connectionPublicIds,
            );
    }

    public function queuePublication(int $publicationId): int
    {
        return SocialPublicationChannelService::fromDatabase()
            ->queuePublication($publicationId);
    }

    /** @return array<string,ChannelAdapter> */
    public function adapters(): array
    {
        return ChannelAdapterRegistry::all();
    }
}
