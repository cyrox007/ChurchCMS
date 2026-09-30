<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\SecretVault;
use ChurchCMS\Modules\Publications\Publication;
use ChurchCMS\Modules\Publications\PublicationRepository;
use DateTimeImmutable;
use Throwable;

final class ChannelOutboundDispatcher
{
    public function __construct(
        private readonly SocialPostRepository $outbox,
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
     * @return array{
     *     claimed:int,
     *     sent:int,
     *     retried:int,
     *     dead_letter:int
     * }
     */
    public function dispatch(
        ?int $limit = null,
        ?int $maxAttempts = null,
    ): array {
        $limit ??= (int) Config::get(
            'social.dispatch_batch_size',
            20,
        );
        $maxAttempts ??= (int) Config::get(
            'social.max_attempts',
            5,
        );

        $limit = max(1, min(100, $limit));
        $maxAttempts = max(1, min(100, $maxAttempts));

        $posts = $this->outbox->claimPending($limit);
        $summary = [
            'claimed' => count($posts),
            'sent' => 0,
            'retried' => 0,
            'dead_letter' => 0,
        ];

        foreach ($posts as $post) {
            $beforeAttempts = $post->attempts;

            try {
                $this->dispatchOne($post);
                $summary['sent']++;
            } catch (Throwable $error) {
                $this->outbox->markFailed(
                    $post->id,
                    self::safeError($error),
                    $maxAttempts,
                );

                if ($beforeAttempts + 1 >= $maxAttempts) {
                    $summary['dead_letter']++;
                } else {
                    $summary['retried']++;
                }
            }
        }

        return $summary;
    }

    private function dispatchOne(SocialPost $post): void
    {
        $connection = $this->connections->findById(
            $post->connectionId,
        );

        if (
            $connection === null
            || !$connection->enabled
            || !$connection->outboundEnabled
        ) {
            throw new RuntimeException(
                'Подключение внешнего канала недоступно для отправки.'
            );
        }

        $adapter = ChannelAdapterRegistry::get(
            $connection->provider,
        );
        if ($adapter === null) {
            throw new RuntimeException(
                'Для внешнего канала не зарегистрирован адаптер.'
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
            throw new RuntimeException(
                'Исходная публикация недоступна для внешней отправки.'
            );
        }

        $item = $this->outboundItem(
            $publication,
            $post,
        );

        if (!self::canPublish(
            $adapter,
            $item,
        )) {
            throw new RuntimeException(
                'Адаптер внешнего канала не поддерживает доступный тип публикации.'
            );
        }

        $credentials = SecretVault::decrypt(
            $connection->tokenEncrypted,
        );
        $result = $adapter->publish(
            $connection,
            $credentials,
            $item,
        );

        if (!$result->success) {
            throw new RuntimeException(
                trim((string) $result->error) !== ''
                    ? (string) $result->error
                    : 'Адаптер не подтвердил внешнюю публикацию.'
            );
        }

        $this->outbox->markSent(
            $post->id,
            $result->remoteId,
        );
    }

    private function outboundItem(
        Publication $publication,
        SocialPost $post,
    ): ChannelOutboundItem {
        $baseUrl = rtrim(
            (string) Config::get(
                'syndication.site_url',
                Config::get('app.url', ''),
            ),
            '/',
        );
        $canonicalUrl = $baseUrl
            . '/publications/'
            . rawurlencode($publication->slug);

        $text = trim((string) $post->customText);
        if ($text === '') {
            $text = trim($publication->excerpt);
        }
        if ($text === '') {
            $text = $publication->title;
        }

        $media = [];
        $mediaCapability = ModuleRuntimeLoader::capability(
            'media',
            'media.resumable-upload',
        );

        if (
            $mediaCapability !== null
            && method_exists(
                $mediaCapability,
                'publicationVideo',
            )
        ) {
            $video = $mediaCapability->publicationVideo(
                $publication->publicId,
                $publication->siteKey,
            );

            if (is_array($video)) {
                $media[] = $video;
            }
        }

        return new ChannelOutboundItem(
            sourceId: $publication->publicId,
            kind: $publication->type->value,
            title: $publication->title,
            text: $text,
            canonicalUrl: $canonicalUrl,
            media: $media,
        );
    }

    private static function canPublish(
        ChannelAdapter $adapter,
        ChannelOutboundItem $item,
    ): bool {
        $capabilities = $adapter->capabilities();

        if (
            in_array(
                ChannelCapability::PUBLISH_TEXT,
                $capabilities,
                true,
            )
            || in_array(
                ChannelCapability::PUBLISH_LINK,
                $capabilities,
                true,
            )
        ) {
            return true;
        }

        if (
            !in_array(
                ChannelCapability::PUBLISH_VIDEO,
                $capabilities,
                true,
            )
        ) {
            return false;
        }

        foreach ($item->media as $media) {
            if (
                is_array($media)
                && ($media['type'] ?? null) === 'video'
                && is_string($media['public_id'] ?? null)
                && trim((string) $media['public_id']) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    private static function safeError(Throwable $error): string
    {
        $message = str_replace(
            ["\r", "\n", "\0"],
            [' ', ' ', ''],
            trim($error->getMessage()),
        );

        if ($message === '') {
            $message = $error::class;
        }

        return substr($message, 0, 500);
    }
}
