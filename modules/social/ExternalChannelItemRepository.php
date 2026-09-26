<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use DateTimeImmutable;
use PDO;

final class ExternalChannelItemRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function store(int $connectionId, ChannelInboundItem $item): void
    {
        $fingerprint = hash('sha256', json_encode([
            $item->kind,
            $item->title,
            $item->text,
            $item->canonicalUrl,
            $item->media,
            $item->publishedAt?->format(DATE_ATOM),
            $item->updatedAt?->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $existing = $this->pdo->prepare(
            'SELECT id, fingerprint FROM external_channel_items
             WHERE connection_id = :connection_id AND remote_id = :remote_id
             LIMIT 1'
        );
        $existing->execute([
            'connection_id' => $connectionId,
            'remote_id' => $item->remoteId,
        ]);
        $row = $existing->fetch();

        $now = gmdate('Y-m-d H:i:s');
        $media = json_encode($item->media, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payload = json_encode($item->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (is_array($row)) {
            if (hash_equals((string) $row['fingerprint'], $fingerprint)) {
                return;
            }

            $update = $this->pdo->prepare(
                'UPDATE external_channel_items
                 SET kind = :kind,
                     title = :title,
                     body_text = :body_text,
                     canonical_url = :canonical_url,
                     media_json = :media_json,
                     payload_json = :payload_json,
                     remote_published_at = :remote_published_at,
                     remote_updated_at = :remote_updated_at,
                     fingerprint = :fingerprint,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                'kind' => $item->kind,
                'title' => $item->title,
                'body_text' => $item->text,
                'canonical_url' => $item->canonicalUrl,
                'media_json' => $media,
                'payload_json' => $payload,
                'remote_published_at' => $item->publishedAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'remote_updated_at' => $item->updatedAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'fingerprint' => $fingerprint,
                'updated_at' => $now,
                'id' => (int) $row['id'],
            ]);
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO external_channel_items (
                public_id, connection_id, remote_id, kind, title, body_text,
                canonical_url, media_json, payload_json, remote_published_at,
                remote_updated_at, fingerprint, status, linked_publication_id,
                discovered_at, updated_at
             ) VALUES (
                :public_id, :connection_id, :remote_id, :kind, :title, :body_text,
                :canonical_url, :media_json, :payload_json, :remote_published_at,
                :remote_updated_at, :fingerprint, :status, NULL,
                :discovered_at, :updated_at
             )'
        );
        $insert->execute([
            'public_id' => Uuid::v4(),
            'connection_id' => $connectionId,
            'remote_id' => $item->remoteId,
            'kind' => $item->kind,
            'title' => $item->title,
            'body_text' => $item->text,
            'canonical_url' => $item->canonicalUrl,
            'media_json' => $media,
            'payload_json' => $payload,
            'remote_published_at' => $item->publishedAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'remote_updated_at' => $item->updatedAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'fingerprint' => $fingerprint,
            'status' => 'pending',
            'discovered_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return list<ExternalChannelItem> */
    public function pending(int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));

        $statement = $this->pdo->prepare(
            'SELECT * FROM external_channel_items
             WHERE status = :status
             ORDER BY discovered_at DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':status', 'pending');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn(array $row): ExternalChannelItem => $this->hydrate($row), $statement->fetchAll());
    }

    public function findByPublicId(string $publicId): ?ExternalChannelItem
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM external_channel_items WHERE public_id = :public_id LIMIT 1'
        );
        $statement->execute(['public_id' => $publicId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function markIgnored(string $publicId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE external_channel_items SET status = :status, updated_at = :updated_at
             WHERE public_id = :public_id'
        );
        $statement->execute([
            'status' => 'ignored',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);
    }

    public function linkToPublication(string $publicId, int $publicationId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE external_channel_items
             SET status = :status, linked_publication_id = :publication_id, updated_at = :updated_at
             WHERE public_id = :public_id'
        );
        $statement->execute([
            'status' => 'imported',
            'publication_id' => $publicationId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);
    }

    private function hydrate(array $row): ExternalChannelItem
    {
        $media = json_decode((string) ($row['media_json'] ?? '[]'), true);
        if (!is_array($media)) {
            $media = [];
        }

        return new ExternalChannelItem(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            connectionId: (int) $row['connection_id'],
            remoteId: (string) $row['remote_id'],
            kind: (string) $row['kind'],
            title: isset($row['title']) && $row['title'] !== '' ? (string) $row['title'] : null,
            bodyText: (string) ($row['body_text'] ?? ''),
            canonicalUrl: isset($row['canonical_url']) && $row['canonical_url'] !== '' ? (string) $row['canonical_url'] : null,
            media: $media,
            status: (string) $row['status'],
            linkedPublicationId: isset($row['linked_publication_id']) ? (int) $row['linked_publication_id'] : null,
            remotePublishedAt: !empty($row['remote_published_at']) ? new DateTimeImmutable((string) $row['remote_published_at']) : null,
            remoteUpdatedAt: !empty($row['remote_updated_at']) ? new DateTimeImmutable((string) $row['remote_updated_at']) : null,
            discoveredAt: new DateTimeImmutable((string) $row['discovered_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
        );
    }
}
