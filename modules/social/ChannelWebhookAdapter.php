<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

interface ChannelWebhookAdapter
{
    public function verifyWebhook(
        SocialConnection $connection,
        string $credentials,
        ChannelWebhookRequest $request,
    ): bool;

    public function receiveWebhook(
        SocialConnection $connection,
        string $credentials,
        ChannelWebhookRequest $request,
    ): ChannelWebhookResult;
}
