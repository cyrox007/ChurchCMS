<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use RuntimeException;

final class ChannelWebhookException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
