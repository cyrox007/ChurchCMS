<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class RutubeUploadResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $remoteId = null,
        public readonly ?string $remoteUrl = null,
        public readonly ?string $error = null,
        public readonly bool $retryable = true,
    ) {
    }
}
