<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\SecretVault;
use PDO;
use RuntimeException;

final class FederationRepository
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

    /**
     * @return list<FederationLink>
     */
    public function links(
        string $siteKey = 'default',
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT * FROM organization_federation_links
             WHERE site_key = :site_key
             ORDER BY relation ASC, remote_name ASC, id ASC'
        );
        $statement->execute([
            'site_key' => $siteKey,
        ]);

        return array_map(
            fn(array $row): FederationLink =>
                $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    public function findByPublicId(
        string $publicId,
        string $siteKey = 'default',
    ): ?FederationLink {
        $statement = $this->pdo->prepare(
            'SELECT * FROM organization_federation_links
             WHERE public_id = :public_id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $this->hydrate($row)
            : null;
    }

    public function findByRemoteInstanceId(
        string $remoteInstanceId,
        string $siteKey = 'default',
    ): ?FederationLink {
        $statement = $this->pdo->prepare(
            'SELECT * FROM organization_federation_links
             WHERE remote_instance_id = :remote_instance_id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'remote_instance_id' => $remoteInstanceId,
            'site_key' => $siteKey,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $this->hydrate($row)
            : null;
    }

    public function outboundToken(
        int $linkId,
    ): ?string {
        if ($linkId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT outbound_token_encrypted
             FROM organization_federation_links
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $linkId]);

        $encrypted = $statement->fetchColumn();

        if (!is_string($encrypted) || $encrypted === '') {
            return null;
        }

        return SecretVault::decrypt($encrypted);
    }

    private function hydrate(array $row): FederationLink
    {
        return new FederationLink(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            localOrganizationId: (int) $row['local_organization_id'],
            relation: (string) $row['relation'],
            status: (string) $row['status'],
            remoteInstanceId: (string) $row['remote_instance_id'],
            remoteOrganizationPublicId: (string) $row['remote_organization_public_id'],
            remoteBaseUrl: (string) $row['remote_base_url'],
            remoteProfile: isset($row['remote_profile'])
                && $row['remote_profile'] !== ''
                ? (string) $row['remote_profile']
                : null,
            remoteName: isset($row['remote_name'])
                && $row['remote_name'] !== ''
                ? (string) $row['remote_name']
                : null,
            inboundScopes: self::decodeScopes(
                (string) ($row['inbound_scopes_json'] ?? '[]'),
            ),
            outboundScopes: self::decodeScopes(
                (string) ($row['outbound_scopes_json'] ?? '[]'),
            ),
            syncCursor: isset($row['sync_cursor'])
                && $row['sync_cursor'] !== ''
                ? (string) $row['sync_cursor']
                : null,
            lastSeenAt: isset($row['last_seen_at'])
                && $row['last_seen_at'] !== ''
                ? (string) $row['last_seen_at']
                : null,
            lastError: isset($row['last_error'])
                && $row['last_error'] !== ''
                ? (string) $row['last_error']
                : null,
        );
    }

    /**
     * @return list<string>
     */
    private static function decodeScopes(string $json): array
    {
        try {
            $decoded = json_decode(
                $json,
                true,
                32,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $error) {
            throw new RuntimeException(
                'Повреждён список scopes федеративной связи.',
                0,
                $error,
            );
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            $decoded,
            static fn(mixed $scope): bool =>
                is_string($scope)
                && preg_match(
                    '/^[a-z][a-z0-9_.:-]{1,63}$/D',
                    $scope,
                ) === 1,
        ));
    }
}
