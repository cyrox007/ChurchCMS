<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

final class DistributionWebhookSignature
{
    public static function sign(string $secret, string $timestamp, string $payload): string
    {
        return 'sha256=' . hash_hmac(
            'sha256',
            $timestamp . '.' . $payload,
            $secret,
        );
    }
}
