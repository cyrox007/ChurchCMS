<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Modules\Publications\PublicationRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class SocialOutboundFailureService
{
    public function __construct(
        private readonly SocialPostRepository $posts,
        private readonly SocialConnectionRepository $connections,
        private readonly PublicationRepository $publications,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            SocialPostRepository::fromDatabase(),
            SocialConnectionRepository::fromDatabase(),
            PublicationRepository::fromDatabase(),
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function failures(int $limit = 100): array
    {
        return $this->posts->failedForAdmin($limit);
    }

    public function retry(string $postPublicId): SocialPost
    {
        $post = $this->posts->findByPublicId(
            trim($postPublicId),
        );
        if (
            $post === null
            || $post->status !== 'failed'
            || !$post->enabled
        ) {
            throw new InvalidArgumentException(
                'Ошибка отправки не найдена или уже обработана.'
            );
        }

        $connection = $this->connections->findById(
            $post->connectionId,
        );
        if (
            $connection === null
            || !$connection->enabled
            || !$connection->outboundEnabled
        ) {
            throw new InvalidArgumentException(
                'Подключение внешнего канала сейчас недоступно.'
            );
        }

        $publication = $this->publications->findById(
            $post->publicationId,
        );
        if (
            $publication === null
            || !$publication->isPublicNow(
                new DateTimeImmutable('now'),
            )
        ) {
            throw new InvalidArgumentException(
                'Исходная публикация больше не доступна для отправки.'
            );
        }

        if (!$this->posts->retryFailed($post->id)) {
            throw new InvalidArgumentException(
                'Ошибка отправки уже была обработана другим процессом.'
            );
        }

        $updated = $this->posts->findByPublicId(
            $post->publicId,
        );
        if ($updated === null) {
            throw new \RuntimeException(
                'Повторно поставленную запись не удалось перечитать.'
            );
        }

        return $updated;
    }
}
