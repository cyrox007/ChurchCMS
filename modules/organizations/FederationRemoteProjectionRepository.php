<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use PDO;
use RuntimeException;

final class FederationRemoteProjectionRepository
{
    private const MAX_PAYLOAD_BYTES = 1_000_000;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function find(
        int $federationLinkId,
        string $objectType,
        string $remotePublicId,
    ): ?FederationRemoteProjection {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM federation_remote_projections
             WHERE federation_link_id = :link_id
               AND object_type = :object_type
               AND remote_public_id = :remote_public_id
             LIMIT 1'
        );
        $statement->execute([
            'link_id' => $federationLinkId,
            'object_type' => $objectType,
            'remote_public_id' => $remotePublicId,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $this->hydrate($row)
            : null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return 'created'|'updated'|'ignored'
     */
    public function upsert(
        int $federationLinkId,
        string $objectType,
        string $remotePublicId,
        ?string $remoteOwnerOrganizationPublicId,
        ?string $canonicalUrl,
        DateTimeImmutable $remoteUpdatedAt,
        array $payload,
    ): string {
        $payloadJson = self::payloadJson($payload);
        $remoteTimestamp = self::utcTimestamp(
            $remoteUpdatedAt,
        );
        $existing = $this->existingRow(
            $federationLinkId,
            $objectType,
            $remotePublicId,
        );

        if (
            is_array($existing)
            && $remoteTimestamp
                <= (string) $existing['remote_updated_at']
        ) {
            return 'ignored';
        }

        $now = gmdate('Y-m-d H:i:s');

        if (is_array($existing)) {
            $statement = $this->pdo->prepare(
                'UPDATE federation_remote_projections
                 SET remote_owner_organization_public_id = :owner,
                     canonical_url = :canonical_url,
                     state = :state,
                     payload_json = :payload_json,
                     remote_updated_at = :remote_updated_at,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $statement->execute([
                'owner' => $remoteOwnerOrganizationPublicId,
                'canonical_url' => $canonicalUrl,
                'state' => 'active',
                'payload_json' => $payloadJson,
                'remote_updated_at' => $remoteTimestamp,
                'updated_at' => $now,
                'id' => (int) $existing['id'],
            ]);

            return 'updated';
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO federation_remote_projections (
                public_id,
                federation_link_id,
                object_type,
                remote_public_id,
                remote_owner_organization_public_id,
                canonical_url,
                state,
                payload_json,
                remote_updated_at,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :link_id,
                :object_type,
                :remote_public_id,
                :owner,
                :canonical_url,
                :state,
                :payload_json,
                :remote_updated_at,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => Uuid::v4(),
            'link_id' => $federationLinkId,
            'object_type' => $objectType,
            'remote_public_id' => $remotePublicId,
            'owner' => $remoteOwnerOrganizationPublicId,
            'canonical_url' => $canonicalUrl,
            'state' => 'active',
            'payload_json' => $payloadJson,
            'remote_updated_at' => $remoteTimestamp,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 'created';
    }

    /**
     * Tombstone с тем же временем имеет приоритет над active-проекцией.
     *
     * @return 'created'|'updated'|'ignored'
     */
    public function tombstone(
        int $federationLinkId,
        string $objectType,
        string $remotePublicId,
        ?string $remoteOwnerOrganizationPublicId,
        DateTimeImmutable $remoteUpdatedAt,
    ): string {
        $remoteTimestamp = self::utcTimestamp(
            $remoteUpdatedAt,
        );
        $existing = $this->existingRow(
            $federationLinkId,
            $objectType,
            $remotePublicId,
        );

        if (
            is_array($existing)
            && $remoteTimestamp
                < (string) $existing['remote_updated_at']
        ) {
            return 'ignored';
        }

        $now = gmdate('Y-m-d H:i:s');

        if (is_array($existing)) {
            $owner = $remoteOwnerOrganizationPublicId
                ?? self::nullableString(
                    $existing['remote_owner_organization_public_id']
                    ?? null,
                );

            $statement = $this->pdo->prepare(
                'UPDATE federation_remote_projections
                 SET remote_owner_organization_public_id = :owner,
                     state = :state,
                     payload_json = NULL,
                     remote_updated_at = :remote_updated_at,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $statement->execute([
                'owner' => $owner,
                'state' => 'deleted',
                'remote_updated_at' => $remoteTimestamp,
                'updated_at' => $now,
                'id' => (int) $existing['id'],
            ]);

            return 'updated';
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO federation_remote_projections (
                public_id,
                federation_link_id,
                object_type,
                remote_public_id,
                remote_owner_organization_public_id,
                canonical_url,
                state,
                payload_json,
                remote_updated_at,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :link_id,
                :object_type,
                :remote_public_id,
                :owner,
                NULL,
                :state,
                NULL,
                :remote_updated_at,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => Uuid::v4(),
            'link_id' => $federationLinkId,
            'object_type' => $objectType,
            'remote_public_id' => $remotePublicId,
            'owner' => $remoteOwnerOrganizationPublicId,
            'state' => 'deleted',
            'remote_updated_at' => $remoteTimestamp,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 'created';
    }

    /**
     * @return list<FederationRemoteProjection>
     */
    public function active(
        int $federationLinkId,
        string $objectType,
        int $limit = 100,
    ): array {
        $limit = max(1, min(200, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM federation_remote_projections
             WHERE federation_link_id = :link_id
               AND object_type = :object_type
               AND state = :state
             ORDER BY remote_updated_at DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue(
            ':link_id',
            $federationLinkId,
            PDO::PARAM_INT,
        );
        $statement->bindValue(':object_type', $objectType);
        $statement->bindValue(':state', 'active');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn(array $row): FederationRemoteProjection =>
                $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function existingRow(
        int $federationLinkId,
        string $objectType,
        string $remotePublicId,
    ): ?array {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM federation_remote_projections
             WHERE federation_link_id = :link_id
               AND object_type = :object_type
               AND remote_public_id = :remote_public_id
             LIMIT 1'
        );
        $statement->execute([
            'link_id' => $federationLinkId,
            'object_type' => $objectType,
            'remote_public_id' => $remotePublicId,
        ]);

        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function payloadJson(array $payload): string
    {
        try {
            $json = json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $error) {
            throw new InvalidArgumentException(
                'Projection payload не сериализуется в JSON.',
                0,
                $error,
            );
        }

        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            throw new InvalidArgumentException(
                'Projection payload превышает допустимый размер.'
            );
        }

        return $json;
    }

    private static function utcTimestamp(
        DateTimeImmutable $value,
    ): string {
        return $value
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    private function hydrate(
        array $row,
    ): FederationRemoteProjection {
        $payload = [];
        $rawPayload = $row['payload_json'] ?? null;

        if (is_string($rawPayload) && $rawPayload !== '') {
            try {
                $decoded = json_decode(
                    $rawPayload,
                    true,
                    64,
                    JSON_THROW_ON_ERROR,
                );
            } catch (JsonException $error) {
                throw new RuntimeException(
                    'Повреждён payload remote projection.',
                    0,
                    $error,
                );
            }

            if (!is_array($decoded)) {
                throw new RuntimeException(
                    'Payload remote projection имеет неверный формат.'
                );
            }

            $payload = $decoded;
        }

        return new FederationRemoteProjection(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            federationLinkId: (int) $row['federation_link_id'],
            objectType: (string) $row['object_type'],
            remotePublicId: (string) $row['remote_public_id'],
            remoteOwnerOrganizationPublicId:
                self::nullableString(
                    $row['remote_owner_organization_public_id']
                    ?? null,
                ),
            canonicalUrl: self::nullableString(
                $row['canonical_url'] ?? null,
            ),
            state: (string) $row['state'],
            payload: $payload,
            remoteUpdatedAt: new DateTimeImmutable(
                (string) $row['remote_updated_at'],
                new DateTimeZone('UTC'),
            ),
            createdAt: new DateTimeImmutable(
                (string) $row['created_at'],
                new DateTimeZone('UTC'),
            ),
            updatedAt: new DateTimeImmutable(
                (string) $row['updated_at'],
                new DateTimeZone('UTC'),
            ),
        );
    }

    private static function nullableString(
        mixed $value,
    ): ?string {
        return is_string($value) && $value !== ''
            ? $value
            : null;
    }
}
