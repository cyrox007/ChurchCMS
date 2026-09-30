<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final readonly class MediaUsageReference
{
    public function __construct(
        public int $id,
        public string $siteKey,
        public string $mediaPublicId,
        public string $consumerType,
        public string $consumerPublicId,
        public string $usageKey,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
