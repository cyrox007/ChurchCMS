<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final readonly class MediaBinaryChunk
{
    public function __construct(
        public int $offset,
        public string $data,
        public int $nextOffset,
        public int $totalBytes,
    ) {
    }

    public function bytes(): int
    {
        return strlen($this->data);
    }

    public function complete(): bool
    {
        return $this->nextOffset >= $this->totalBytes;
    }
}
