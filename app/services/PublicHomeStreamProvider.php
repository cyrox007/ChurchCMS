<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

interface PublicHomeStreamProvider
{
    public function id(): string;

    public function label(): string;

    public function priority(): int;

    /** @return list<array<string,mixed>> */
    public function items(string $siteKey = 'default', int $limit = 6): array;
}
