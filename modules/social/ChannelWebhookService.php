<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\SecretVault;
use InvalidArgumentException;
use Throwable;

final class ChannelWebhookService
{
    public function __construct(
        private readonly SocialConnectionRepository $connections,
        private readonly ExternalChannelItemRepository $inbox,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            SocialConnectionRepository::fromDatabase(),
            ExternalChannelItemRepository::fromDatabase(),
        );
    }

    public function receive(
        string $connectionPublicId,
        ChannelWebhookRequest $request,
    ): ChannelWebhookResult {
        $connection = $this->connections->findByPublicId(
            trim($connectionPublicId),
        );

        if (
            $connection === null
            || !$connection->enabled
            || !$connection->inboundEnabled
        ) {
            throw new ChannelWebhookException(
                'Webhook connection is unavailable.',
                404,
            );
        }

        $adapter = ChannelAdapterRegistry::get(
            $connection->provider,
        );
        if (
            $adapter === null
            || !$adapter instanceof ChannelWebhookAdapter
            || !in_array(
                ChannelCapability::WEBHOOK,
                $adapter->capabilities(),
                true,
            )
        ) {
            throw new ChannelWebhookException(
                'Webhook adapter is unavailable.',
                404,
            );
        }

        try {
            $credentials = SecretVault::decrypt(
                $connection->tokenEncrypted,
            );
        } catch (Throwable $error) {
            throw new ChannelWebhookException(
                'Webhook credentials cannot be loaded.',
                500,
            );
        }

        try {
            $verified = $adapter->verifyWebhook(
                $connection,
                $credentials,
                $request,
            );
        } catch (Throwable $error) {
            throw new ChannelWebhookException(
                'Webhook signature verification failed.',
                401,
            );
        }

        if (!$verified) {
            throw new ChannelWebhookException(
                'Webhook signature is invalid.',
                401,
            );
        }

        try {
            $result = $adapter->receiveWebhook(
                $connection,
                $credentials,
                $request,
            );
        } catch (InvalidArgumentException $error) {
            throw new ChannelWebhookException(
                'Webhook payload is invalid.',
                400,
            );
        } catch (Throwable $error) {
            throw new ChannelWebhookException(
                'Webhook adapter failed.',
                500,
            );
        }

        foreach ($result->items as $item) {
            $this->inbox->store(
                $connection->id,
                $item,
            );
        }

        return $result;
    }
}
