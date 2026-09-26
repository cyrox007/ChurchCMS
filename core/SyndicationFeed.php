<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use InvalidArgumentException;

final class SyndicationFeed
{
    /**
     * @param list<SyndicationEntry> $entries
     */
    public function __construct(
        public readonly string $title,
        public readonly string $siteUrl,
        public readonly string $description,
        public readonly array $entries,
    ) {
        if ($title === '' || $description === '') {
            throw new InvalidArgumentException('Feed title and description are required.');
        }

        if (filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Feed site URL must be absolute.');
        }
    }
}
