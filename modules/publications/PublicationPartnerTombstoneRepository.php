<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

/**
 * Хранит последний известный tombstone публикации для partner sync.
 *
 * Tombstone отделён от основной строки публикации, чтобы последующее
 * редактирование или повторная публикация не стирали факт снятия материала.
 */
final class PublicationPartnerTombstoneRepository
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
        Publication $publication,
        string $reason = 'withdrawn',
        ?DateTimeImmutable $withdrawnAt = null,
    ): void {
        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,31}$/D',
                $reason,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректная причина tombstone публикации.'
            );
        }

        $timestamp = ($withdrawnAt ?? new DateTimeImmutable('now'))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $find = $this->pdo->prepare(
            'SELECT id
             FROM publication_partner_tombstones
             WHERE site_key = :site_key
               AND publication_public_id = :publication_public_id
             LIMIT 1'
        );
        $find->execute([
            'site_key' => $publication->siteKey,
            'publication_public_id' => $publication->publicId,
        ]);

        $id = $find->fetchColumn();

        if ($id !== false) {
            $update = $this->pdo->prepare(
                'UPDATE publication_partner_tombstones
                 SET organization_owner_public_id = :owner,
                     reason = :reason,
                     withdrawn_at = :withdrawn_at,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                'owner' => $publication->ownerOrganizationPublicId,
                'reason' => $reason,
                'withdrawn_at' => $timestamp,
                'updated_at' => $timestamp,
                'id' => (int) $id,
            ]);

            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO publication_partner_tombstones (
                site_key,
                publication_public_id,
                organization_owner_public_id,
                reason,
                withdrawn_at,
                created_at,
                updated_at
             ) VALUES (
                :site_key,
                :publication_public_id,
                :owner,
                :reason,
                :withdrawn_at,
                :created_at,
                :updated_at
             )'
        );
        $insert->execute([
            'site_key' => $publication->siteKey,
            'publication_public_id' => $publication->publicId,
            'owner' => $publication->ownerOrganizationPublicId,
            'reason' => $reason,
            'withdrawn_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * @return list<array{
     *     id:int,
     *     publication_public_id:string,
     *     organization_owner_public_id:?string,
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
                'Некорректный public ID курсора tombstone.'
            );
        }

        $cursorSql = $afterPublicId === null
            ? 'updated_at > :updated_since'
            : '(updated_at > :updated_since
                OR (
                    updated_at = :updated_since
                    AND publication_public_id > :after_public_id
                ))';

        $statement = $this->pdo->prepare(
            'SELECT id,
                    publication_public_id,
                    organization_owner_public_id,
                    reason,
                    withdrawn_at,
                    updated_at
             FROM publication_partner_tombstones
             WHERE site_key = :site_key
               AND ' . $cursorSql . '
             ORDER BY updated_at ASC, publication_public_id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':site_key', $siteKey);
        $statement->bindValue(':updated_since', $timestamp);
        if ($afterPublicId !== null) {
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
                'publication_public_id' =>
                    (string) $row['publication_public_id'],
                'organization_owner_public_id' =>
                    isset($row['organization_owner_public_id'])
                    && $row['organization_owner_public_id'] !== ''
                        ? (string) $row['organization_owner_public_id']
                        : null,
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
