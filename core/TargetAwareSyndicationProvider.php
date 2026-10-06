<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

interface TargetAwareSyndicationProvider extends SyndicationProvider
{
    /** @return iterable<SyndicationEntry> */
    public function entriesForTarget(string $target): iterable;
}
