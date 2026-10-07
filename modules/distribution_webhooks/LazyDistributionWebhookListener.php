<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\DistributionEventListener;

final class LazyDistributionWebhookListener implements DistributionEventListener
{
    /** @param array<string,mixed> $payload */
    public function enqueue(string $eventType, array $payload): void
    {
        $publicId = trim((string) ($payload['public_id'] ?? ''));
        if ($publicId === '') {
            return;
        }

        $pdo = DatabaseManager::getInstance()->connection();
        $statement = $pdo->prepare(
            'SELECT public_id, site_key, owner_organization_public_id, type, status, '
            . 'slug, title, excerpt, published_at, updated_at '
            . 'FROM publications WHERE public_id = :public_id LIMIT 1'
        );
        $statement->execute([':public_id' => $publicId]);
        $publication = $statement->fetch();
        if (!is_array($publication)) {
            return;
        }

        $siteKey = (string) $publication['site_key'];
        $eventPayload = [
            'public_id' => (string) $publication['public_id'],
            'site_key' => $siteKey,
            'type' => (string) $publication['type'],
            'status' => (string) $publication['status'],
            'slug' => (string) $publication['slug'],
            'title' => (string) $publication['title'],
            'excerpt' => (string) $publication['excerpt'],
            'published_at' => $publication['published_at'] !== null
                ? (string) $publication['published_at']
                : null,
            'updated_at' => (string) $publication['updated_at'],
            'organization_owner_id' => $publication['owner_organization_public_id'] !== null
                ? (string) $publication['owner_organization_public_id']
                : null,
        ];

        $siteUrl = rtrim((string) Config::get('syndication.site_url', ''), '/');
        if ($siteUrl !== '') {
            $eventPayload['canonical_url'] = $siteUrl
                . '/publications/'
                . rawurlencode((string) $publication['slug']);
        }

        DistributionWebhookService::fromDatabase($siteKey)->enqueue(
            $eventType,
            $eventPayload,
        );
    }
}
