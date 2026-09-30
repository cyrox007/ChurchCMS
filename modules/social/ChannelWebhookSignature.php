<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class ChannelWebhookSignature
{
    public static function verifyHmacSha256(
        string $provided,
        string $secret,
        string $payload,
        string $prefix = 'sha256=',
    ): bool {
        if ($secret === '' || $provided === '') {
            return false;
        }

        $expected = $prefix
            . hash_hmac(
                'sha256',
                $payload,
                $secret,
            );

        return hash_equals($expected, trim($provided));
    }

    public static function verifySecret(
        string $provided,
        string $expected,
    ): bool {
        return $provided !== ''
            && $expected !== ''
            && hash_equals($expected, $provided);
    }
}
