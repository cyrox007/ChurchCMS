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
     *     status:?string,
     *     custom_text:?string
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
                'custom_text' => $post?->customText,
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
     * @param array<string,mixed> $customTexts
     * @return array<string,?string>
     */
    public function validateCustomTexts(
        array $connectionPublicIds,
        array $customTexts,
    ): array {
        $selected = array_fill_keys(
            $this->validateSelection($connectionPublicIds),
            true,
        );
        $result = [];

        foreach ($selected as $publicId => $_) {
            $value = $customTexts[$publicId] ?? null;
            if ($value === null || $value === '') {
                $result[$publicId] = null;
                continue;
            }

            if (!is_string($value)) {
                throw new InvalidArgumentException(
                    'Текст внешнего канала заполнен некорректно.'
                );
            }

            $value = trim($value);
            if ($value === '') {
                $result[$publicId] = null;
                continue;
            }

            $length = function_exists('mb_strlen')
                ? mb_strlen($value, 'UTF-8')
                : strlen($value);

            if ($length > 5000) {
                throw new InvalidArgumentException(
                    'Текст внешнего канала не должен превышать 5000 символов.'
                );
            }

            $result[$publicId] = $value;
        }

        return $result;
    }

    /**
     * @param list<string> $connectionPublicIds
     */
    public function saveSelection(
        int $publicationId,
        array $connectionPublicIds,
        array $customTexts = [],
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

        $validatedTexts = $this->validateCustomTexts(
            $validated,
            $customTexts,
        );
        $connectionIds = [];
        $textsByConnectionId = [];

        foreach ($validated as $publicId) {
            $connectionId = $available[$publicId];
            $connectionIds[] = $connectionId;
            $textsByConnectionId[$connectionId] =
                $validatedTexts[$publicId] ?? null;
        }

        $this->posts->replaceSelection(
            $publicationId,
            $connectionIds,
            $textsByConnectionId,
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
