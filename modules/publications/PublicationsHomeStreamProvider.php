<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\App\Services\PublicHomeStreamProvider;

final class PublicationsHomeStreamProvider implements PublicHomeStreamProvider
{
    public function id(): string
    {
        return 'publications';
    }

    public function label(): string
    {
        return 'Последние публикации';
    }

    public function priority(): int
    {
        return 10;
    }

    public function items(string $siteKey = 'default', int $limit = 6): array
    {
        return (new PublicationsCapability())->latestForPublicTheme($siteKey, $limit);
    }
}
