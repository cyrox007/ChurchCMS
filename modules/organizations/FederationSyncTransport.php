<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

interface FederationSyncTransport
{
    /**
     * Выполняет защищённый GET к partner API удалённого ChurchCMS.
     *
     * @param array<string,string|int> $query
     * @return array<string,mixed>
     */
    public function getJson(
        string $baseUrl,
        string $path,
        array $query,
        string $token,
    ): array;
}
