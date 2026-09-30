<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final readonly class MediaDerivative
{
    public function __construct(
        public int $id,
        public string $siteKey,
        public string $mediaPublicId,
        public string $variant,
        public string $sha256,
        public string $mimeType,
        public int $bytes,
        public int $pixelWidth,
        public int $pixelHeight,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
