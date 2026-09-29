<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use InvalidArgumentException;

final class SocialPublicationChannelService
{
    public function __construct(
        private readonly SocialConnectionRepository $connections,
        private readonly SocialPostRepository $posts,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            SocialConnectionRepository::fromDatabase(),
            SocialPostRepository::fromDatabase(),
        );
    }

    /**
     * @return list<array{
     *     public_id:string,
     *     provider:string,
     *     name:string,
     *     target_ref:string,
     *     selected:bool,
     *     status:?string
     * }>
     */
    public function editorConnections(
        ?int $publicationId,
    ): array {
        $posts = [];
        if ($publicationId !== null && $publicationId > 0) {
            foreach ($this->posts->forPublication($publicationId) as $post) {
                $posts[$post->connectionId] = $post;
            }
        }

        $result = [];
        foreach ($this->connections->outboundEnabled() as $connection) {
            $post = $posts[$connection->id] ?? null;

            $result[] = [
                'public_id' => $connection->publicId,
                'provider' => $connection->provider,
                'name' => $connection->name,
                'target_ref' => $connection->targetRef,
                'selected' => $post?->enabled === true,
                'status' => $post?->status,
            ];
        }

        return $result;
    }

    /**
     * @param list<string> $connectionPublicIds
     * @return list<string>
     */
    public function validateSelection(
        array $connectionPublicIds,
    ): array {
        $available = [];
        foreach ($this->connections->outboundEnabled() as $connection) {
            $available[$connection->publicId] = $connection->id;
        }

        $validated = [];
        foreach ($connectionPublicIds as $publicId) {
            if (!is_string($publicId)) {
                throw new InvalidArgumentException(
                    'Список внешних каналов заполнен некорректно.'
                );
            }

            $publicId = trim($publicId);
            if ($publicId === '') {
                continue;
            }

            if (!isset($available[$publicId])) {
                throw new InvalidArgumentException(
                    'Выбранный внешний канал недоступен для публикации.'
                );
            }

            $validated[$publicId] = true;
        }

        return array_keys($validated);
    }

    /**
     * @param list<string> $connectionPublicIds
     */
    public function saveSelection(
        int $publicationId,
        array $connectionPublicIds,
    ): void {
        if ($publicationId <= 0) {
            throw new InvalidArgumentException(
                'Некорректная публикация для внешних каналов.'
            );
        }

        $validated = $this->validateSelection(
            $connectionPublicIds,
        );
        $available = [];
        foreach ($this->connections->outboundEnabled() as $connection) {
            $available[$connection->publicId] = $connection->id;
        }

        $connectionIds = [];
        foreach ($validated as $publicId) {
            $connectionIds[] = $available[$publicId];
        }

        $this->posts->replaceSelection(
            $publicationId,
            $connectionIds,
        );
    }

    public function queuePublication(int $publicationId): int
    {
        if ($publicationId <= 0) {
            return 0;
        }

        return $this->posts->queueEnabledForPublication(
            $publicationId,
        );
    }
}
