<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use DateTimeImmutable;
use InvalidArgumentException;

final class SyndicationEntry
{
    /**
     * @param list<string> $targets
     * @param list<string> $categories
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $title,
        public readonly string $description,
        public readonly string $contentHtml,
        public readonly DateTimeImmutable $publishedAt,
        public readonly ?DateTimeImmutable $updatedAt = null,
        public readonly ?string $author = null,
        public readonly array $categories = [],
        public readonly ?string $imageUrl = null,
        public readonly ?string $imageMime = null,
        public readonly array $targets = [],
    ) {
        if ($id === '' || $url === '' || $title === '') {
            throw new InvalidArgumentException('Syndication entry id, url and title are required.');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Syndication entry URL must be absolute.');
        }

        foreach ($targets as $target) {
            if (!is_string($target) || preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $target) !== 1) {
                throw new InvalidArgumentException('Invalid syndication target.');
            }
        }

        if ($imageUrl !== null && filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Syndication image URL must be absolute.');
        }
    }

    public function isEnabledFor(string $target): bool
    {
        return in_array($target, $this->targets, true);
    }
}
