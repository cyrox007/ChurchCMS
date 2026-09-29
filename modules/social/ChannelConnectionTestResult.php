<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class ChannelConnectionTestResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
    ) {
    }
}
