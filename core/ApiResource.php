<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

/**
 * Explicit outbound API projection.
 *
 * Domain models and database rows must never be returned directly by external
 * controllers. A resource lists exactly the fields allowed to leave ChurchCMS.
 */
interface ApiResource
{
    /** @return array<string,mixed> */
    public function toApiArray(): array;
}
