<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

interface SyndicationProvider
{
    /**
     * Return only published entries allowed for external distribution.
     *
     * @return iterable<SyndicationEntry>
     */
    public function entries(): iterable;
}
