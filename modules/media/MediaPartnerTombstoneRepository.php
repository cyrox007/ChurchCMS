<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

final class MediaPartnerTombstoneRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function record(
        MediaAsset $asset,
        string $reason = 'visibility_changed',
        ?DateTimeImmutable $withdrawnAt = null,
    ): void {
        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,31}$/D',
                $reason,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректная причина tombstone медиаматериала.'
            );
        }

        $timestamp = ($withdrawnAt ?? new DateTimeImmutable('now'))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $find = $this->pdo->prepare(
            'SELECT id
             FROM media_partner_tombstones
             WHERE site_key = :site_key
               AND media_public_id = :media_public_id
             LIMIT 1'
        );
        $find->execute([
            'site_key' => $asset->siteKey,
            'media_public_id' => $asset->publicId,
        ]);

        $id = $find->fetchColumn();

        if ($id !== false) {
            $update = $this->pdo->prepare(
                'UPDATE media_partner_tombstones
                 SET organization_owner_public_id = :owner,
                     reason = :reason,
                     withdrawn_at = :withdrawn_at,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                'owner' => $asset->ownerOrganizationPublicId,
                'reason' => $reason,
                'withdrawn_at' => $timestamp,
                'updated_at' => $timestamp,
                'id' => (int) $id,
            ]);

            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO media_partner_tombstones (
                site_key,
                media_public_id,
                organization_owner_public_id,
                reason,
                withdrawn_at,
                created_at,
                updated_at
             ) VALUES (
                :site_key,
                :media_public_id,
                :owner,
                :reason,
                :withdrawn_at,
                :created_at,
                :updated_at
             )'
        );
        $insert->execute([
            'site_key' => $asset->siteKey,
            'media_public_id' => $asset->publicId,
            'owner' => $asset->ownerOrganizationPublicId,
            'reason' => $reason,
            'withdrawn_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    public function clear(MediaAsset $asset): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM media_partner_tombstones
             WHERE site_key = :site_key
               AND media_public_id = :media_public_id'
        );
        $statement->execute([
            'site_key' => $asset->siteKey,
            'media_public_id' => $asset->publicId,
        ]);
    }

    /**
     * @return list<array{
     *     id:int,
     *     media_public_id:string,
     *     organization_owner_public_id:string,
     *     reason:string,
     *     withdrawn_at:DateTimeImmutable,
     *     updated_at:DateTimeImmutable
     * }>
     */
    public function updatedSince(
        DateTimeImmutable $updatedSince,
        string $siteKey = 'default',
        int $limit = 100,
        ?string $afterPublicId = null,
    ): array {
        $limit = max(1, min(100, $limit));
        $timestamp = $updatedSince
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        if (
            $afterPublicId !== null
            && preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $afterPublicId,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный public ID курсора tombstone медиаматериала.'
            );
        }

        $cursorSql = $afterPublicId === null
            ? 'updated_at > :updated_since'
            : '(updated_at > :updated_since
                OR (
                    updated_at = :same_updated_at
                    AND media_public_id > :after_public_id
                ))';

        $statement = $this->pdo->prepare(
            'SELECT id,
                    media_public_id,
                    organization_owner_public_id,
                    reason,
                    withdrawn_at,
                    updated_at
             FROM media_partner_tombstones
             WHERE site_key = :site_key
               AND ' . $cursorSql . '
             ORDER BY updated_at ASC, media_public_id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':site_key', $siteKey);
        $statement->bindValue(':updated_since', $timestamp);
        if ($afterPublicId !== null) {
            $statement->bindValue(':same_updated_at', $timestamp);
            $statement->bindValue(
                ':after_public_id',
                $afterPublicId,
            );
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'media_public_id' =>
                    (string) $row['media_public_id'],
                'organization_owner_public_id' =>
                    (string) $row['organization_owner_public_id'],
                'reason' => (string) $row['reason'],
                'withdrawn_at' => new DateTimeImmutable(
                    (string) $row['withdrawn_at'],
                    new DateTimeZone('UTC'),
                ),
                'updated_at' => new DateTimeImmutable(
                    (string) $row['updated_at'],
                    new DateTimeZone('UTC'),
                ),
            ],
            $statement->fetchAll(),
        );
    }
}
