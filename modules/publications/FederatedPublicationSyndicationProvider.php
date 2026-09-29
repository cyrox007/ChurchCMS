<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\SyndicationEntry;
use ChurchCMS\Core\SyndicationProvider;
use DateTimeImmutable;
use Throwable;

final class FederatedPublicationSyndicationProvider implements SyndicationProvider
{
    public function __construct(
        private readonly string $siteKey = 'default',
    ) {
    }

    public function entries(): iterable
    {
        $items = FederatedPublicationFeedService::fromDatabase()
            ->latest(
                $this->siteKey,
                50,
            );

        $entries = [];

        foreach ($items as $item) {
            $source = $item['source'] ?? null;
            if (
                !is_array($source)
                || ($source['kind'] ?? null) !== 'federation'
            ) {
                continue;
            }

            $url = trim((string) ($item['url'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));
            $publishedAt = self::date(
                $item['published_at'] ?? null,
            );
            $updatedAt = self::date(
                $item['updated_at'] ?? null,
            );

            if (
                $url === ''
                || filter_var($url, FILTER_VALIDATE_URL) === false
                || $title === ''
                || $publishedAt === null
            ) {
                continue;
            }

            $instanceId = trim(
                (string) ($source['instance_id'] ?? '')
            );
            $remoteId = trim((string) ($item['id'] ?? ''));
            if ($instanceId === '' || $remoteId === '') {
                continue;
            }

            $sourceUrl = trim(
                (string) ($source['canonical_url'] ?? '')
            );
            if (
                $sourceUrl === ''
                || filter_var(
                    $sourceUrl,
                    FILTER_VALIDATE_URL,
                ) === false
            ) {
                $sourceUrl = $url;
            }

            $type = trim((string) ($item['type'] ?? ''));
            $categories = $type !== ''
                ? [$type]
                : [];

            $entries[] = new SyndicationEntry(
                id: 'urn:churchcms:federation:'
                    . $instanceId
                    . ':publication:'
                    . $remoteId,
                url: $url,
                title: $title,
                description: trim(
                    (string) ($item['excerpt'] ?? '')
                ),
                contentHtml: '',
                publishedAt: $publishedAt,
                updatedAt: $updatedAt,
                author: self::optionalString(
                    $item['author'] ?? null,
                ),
                categories: $categories,
                targets: ['rss'],
                sourceName: self::optionalString(
                    $source['name'] ?? null,
                ),
                sourceUrl: $sourceUrl,
            );
        }

        return $entries;
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function optionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }
}
