<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class SyndicationRegistry
{
    /** @var array<string,SyndicationProvider> */
    private static array $providers = [];

    public static function register(string $id, SyndicationProvider $provider): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $id) !== 1) {
            throw new RuntimeException('Invalid syndication provider id.');
        }

        if (isset(self::$providers[$id])) {
            throw new RuntimeException("Duplicate syndication provider: {$id}");
        }

        self::$providers[$id] = $provider;
    }

    /**
     * @return list<SyndicationEntry>
     */
    public static function entriesFor(string $target, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $entries = [];

        foreach (self::$providers as $provider) {
            $providedEntries = $provider instanceof TargetAwareSyndicationProvider
                ? $provider->entriesForTarget($target)
                : $provider->entries();

            foreach ($providedEntries as $entry) {
                if (!$entry instanceof SyndicationEntry || !$entry->isEnabledFor($target)) {
                    continue;
                }

                $entries[] = $entry;
            }
        }

        usort(
            $entries,
            static fn(SyndicationEntry $a, SyndicationEntry $b): int =>
                $b->publishedAt <=> $a->publishedAt,
        );

        return array_slice($entries, 0, $limit);
    }
}
