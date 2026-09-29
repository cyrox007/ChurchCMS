<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

interface FederationSyncWorker
{
    public function id(): string;

    /**
     * @return array{
     *     links:int,
     *     succeeded:int,
     *     failed:int,
     *     projections:int,
     *     tombstones:int,
     *     pending:int
     * }
     */
    public function run(
        string $siteKey = 'default',
        int $linkLimit = 20,
        int $pageSize = 100,
    ): array;
}
