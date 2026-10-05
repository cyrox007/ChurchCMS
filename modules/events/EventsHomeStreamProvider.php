<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Events;

use ChurchCMS\App\Services\PublicHomeStreamProvider;

final class EventsHomeStreamProvider implements PublicHomeStreamProvider
{
    public function id(): string
    {
        return 'events';
    }

    public function label(): string
    {
        return 'Ближайшие события';
    }

    public function priority(): int
    {
        return 20;
    }

    public function items(string $siteKey = 'default', int $limit = 6): array
    {
        return EventCatalogService::fromDatabase()->upcoming($siteKey, $limit);
    }
}
