<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final readonly class MediaPublicFile
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public int $bytes,
        public string $sha256,
        public string $variant,
    ) {
    }
}
