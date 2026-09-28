<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

interface UpdateReleaseTransport
{
    /**
     * @param callable(string):void $consumer
     */
    public function get(
        string $url,
        int $limit,
        callable $consumer,
    ): void;
}
