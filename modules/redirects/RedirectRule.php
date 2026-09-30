<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Redirects;

final readonly class RedirectRule
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $sourcePath,
        public string $targetPath,
        public int $statusCode,
        public bool $enabled,
        public int $hitCount,
        public ?string $lastHitAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
