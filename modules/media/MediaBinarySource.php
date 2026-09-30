<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final readonly class MediaBinarySource
{
    public function __construct(
        public string $mediaPublicId,
        public string $siteKey,
        public string $path,
        public string $mimeType,
        public int $bytes,
        public string $sha256,
    ) {
    }
}
