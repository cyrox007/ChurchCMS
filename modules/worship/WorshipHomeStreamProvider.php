<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\App\Services\PublicHomeStreamProvider;

final class WorshipHomeStreamProvider implements PublicHomeStreamProvider
{
    public function id(): string
    {
        return 'worship';
    }

    public function label(): string
    {
        return 'Ближайшие богослужения';
    }

    public function priority(): int
    {
        return 30;
    }

    public function items(string $siteKey = 'default', int $limit = 6): array
    {
        return WorshipCatalogService::fromDatabase()->upcoming($siteKey, $limit);
    }
}
