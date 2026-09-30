<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use InvalidArgumentException;

final class ChannelWebhookResult
{
    /**
     * @param list<ChannelInboundItem> $items
     */
    public function __construct(
        public readonly array $items = [],
        public readonly int $status = 200,
        public readonly string $body = 'OK',
        public readonly string $contentType =
            'text/plain; charset=utf-8',
    ) {
        if ($status < 200 || $status > 299) {
            throw new InvalidArgumentException(
                'Webhook adapter response status must be 2xx.'
            );
        }

        if (
            $contentType === ''
            || str_contains($contentType, "\r")
            || str_contains($contentType, "\n")
        ) {
            throw new InvalidArgumentException(
                'Webhook adapter response content type is invalid.'
            );
        }

        foreach ($items as $item) {
            if (!$item instanceof ChannelInboundItem) {
                throw new InvalidArgumentException(
                    'Webhook result contains invalid inbound item.'
                );
            }
        }
    }
}
