<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use RuntimeException;

final class ChannelPublishException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
    ) {
        parent::__construct($message);
    }
}
