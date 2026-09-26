<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use PDO;

final class SocialConnectionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /** @return list<SocialConnection> */
    public function all(bool $enabledOnly = false): array
    {
        $sql = 'SELECT * FROM social_connections';
        if ($enabledOnly) {
            $sql .= ' WHERE enabled = :enabled';
        }
        $sql .= ' ORDER BY name, id';

        $statement = $this->pdo->prepare($sql);
        if ($enabledOnly) {
            $statement->execute(['enabled' => 1]);
        } else {
            $statement->execute();
        }

        return array_map(
            fn(array $row): SocialConnection => $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    /** @return list<SocialConnection> */
    public function inboundEnabled(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM social_connections
             WHERE enabled = :enabled
               AND inbound_enabled = :inbound
             ORDER BY id'
        );
        $statement->execute(['enabled' => 1, 'inbound' => 1]);

        return array_map(
            fn(array $row): SocialConnection => $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    /** @return list<SocialConnection> */
    public function outboundEnabled(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM social_connections
             WHERE enabled = :enabled
               AND outbound_enabled = :outbound
             ORDER BY name, id'
        );
        $statement->execute(['enabled' => 1, 'outbound' => 1]);

        return array_map(
            fn(array $row): SocialConnection => $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    public function findByPublicId(string $publicId): ?SocialConnection
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM social_connections WHERE public_id = :public_id LIMIT 1'
        );
        $statement->execute(['public_id' => $publicId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findById(int $id): ?SocialConnection
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM social_connections WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): SocialConnection
    {
        $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);
        if (!is_array($settings)) {
            $settings = [];
        }

        return new SocialConnection(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            provider: SocialProvider::normalize((string) $row['provider']),
            name: (string) $row['name'],
            targetRef: (string) $row['target_ref'],
            tokenEncrypted: (string) $row['token_encrypted'],
            settings: $settings,
            enabled: self::dbBool($row['enabled'] ?? false),
            outboundEnabled: self::dbBool($row['outbound_enabled'] ?? true),
            inboundEnabled: self::dbBool($row['inbound_enabled'] ?? false),
            inboundPolicy: (string) ($row['inbound_policy'] ?? 'review'),
            connectionKind: (string) ($row['connection_kind'] ?? 'social'),
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
        );
    }

    private static function dbBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 't', 'true', 'yes', 'on'], true);
    }
}
