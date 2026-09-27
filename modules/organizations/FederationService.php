<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\SecretVault;
use ChurchCMS\Core\Uuid;
use InvalidArgumentException;
use PDO;

final class FederationService
{
    private OrganizationRepository $organizations;

    public function __construct(private readonly PDO $pdo)
    {
        $this->organizations = new OrganizationRepository(
            $pdo,
        );
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    /**
     * Регистрирует связь с независимой установкой ChurchCMS.
     *
     * Связь не меняет parent_id локального дерева и не выдаёт
     * удалённой стороне административных прав.
     *
     * @param list<string> $inboundScopes
     * @param list<string> $outboundScopes
     */
    public function connect(
        string $localOrganizationPublicId,
        string $relation,
        string $remoteInstanceId,
        string $remoteOrganizationPublicId,
        string $remoteBaseUrl,
        array $inboundScopes = [],
        array $outboundScopes = [],
        ?string $outboundToken = null,
        ?string $remoteProfile = null,
        ?string $remoteName = null,
        string $siteKey = 'default',
    ): string {
        if (
            !in_array(
                $relation,
                ['parent', 'child', 'peer'],
                true,
            )
        ) {
            throw new InvalidArgumentException(
                'Неизвестный тип федеративной связи.'
            );
        }

        self::assertUuid(
            $remoteInstanceId,
            'remote instance ID',
        );
        self::assertUuid(
            $remoteOrganizationPublicId,
            'remote organization ID',
        );

        $local = $this->organizations->findByPublicId(
            $localOrganizationPublicId,
            $siteKey,
        );
        if ($local === null) {
            throw new InvalidArgumentException(
                'Локальная организация для связи не найдена.'
            );
        }

        $remoteBaseUrl = self::baseUrl(
            $remoteBaseUrl,
            $outboundToken,
        );
        $inboundScopes = self::scopes($inboundScopes);
        $outboundScopes = self::scopes($outboundScopes);

        $encryptedToken = null;
        if (
            $outboundToken !== null
            && trim($outboundToken) !== ''
        ) {
            $encryptedToken = SecretVault::encrypt(
                trim($outboundToken),
            );
        }

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO organization_federation_links (
                public_id,
                site_key,
                local_organization_id,
                relation,
                status,
                remote_instance_id,
                remote_organization_public_id,
                remote_base_url,
                remote_profile,
                remote_name,
                inbound_scopes_json,
                outbound_scopes_json,
                outbound_token_encrypted,
                sync_cursor,
                last_seen_at,
                last_error,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :local_organization_id,
                :relation,
                :status,
                :remote_instance_id,
                :remote_organization_public_id,
                :remote_base_url,
                :remote_profile,
                :remote_name,
                :inbound_scopes_json,
                :outbound_scopes_json,
                :outbound_token_encrypted,
                NULL,
                NULL,
                NULL,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'local_organization_id' => $local->id,
            'relation' => $relation,
            'status' => 'pending',
            'remote_instance_id' => $remoteInstanceId,
            'remote_organization_public_id' =>
                $remoteOrganizationPublicId,
            'remote_base_url' => $remoteBaseUrl,
            'remote_profile' => self::optional(
                $remoteProfile,
                64,
            ),
            'remote_name' => self::optional(
                $remoteName,
                255,
            ),
            'inbound_scopes_json' => json_encode(
                $inboundScopes,
                JSON_THROW_ON_ERROR,
            ),
            'outbound_scopes_json' => json_encode(
                $outboundScopes,
                JSON_THROW_ON_ERROR,
            ),
            'outbound_token_encrypted' => $encryptedToken,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function scopes(array $values): array
    {
        $result = [];

        foreach ($values as $value) {
            if (
                !is_string($value)
                || preg_match(
                    '/^[a-z][a-z0-9_.:-]{1,63}$/D',
                    $value,
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Некорректный scope федеративной связи.'
                );
            }

            $result[$value] = true;
        }

        return array_keys($result);
    }

    private static function baseUrl(
        string $value,
        ?string $outboundToken,
    ): string {
        $value = rtrim(trim($value), '/');
        $parts = parse_url($value);

        if (
            !is_array($parts)
            || !in_array(
                $parts['scheme'] ?? null,
                ['https', 'http'],
                true,
            )
            || empty($parts['host'])
            || isset(
                $parts['user'],
                $parts['pass'],
                $parts['query'],
                $parts['fragment'],
            )
        ) {
            throw new InvalidArgumentException(
                'Некорректный адрес удалённого ChurchCMS-узла.'
            );
        }

        if (
            ($parts['scheme'] ?? '') !== 'https'
            && $outboundToken !== null
            && trim($outboundToken) !== ''
            && !self::isLocalOrPrivateHost(
                (string) $parts['host'],
            )
        ) {
            throw new InvalidArgumentException(
                'Credential федеративной связи нельзя отправлять по открытому HTTP.'
            );
        }

        return $value;
    }

    private static function isLocalOrPrivateHost(
        string $host,
    ): bool {
        $host = trim($host, '[]');

        if (
            in_array(
                strtolower($host),
                ['localhost', '127.0.0.1', '::1'],
                true,
            )
        ) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE
            | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    private static function assertUuid(
        string $value,
        string $label,
    ): void {
        if (
            preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный ' . $label . '.'
            );
        }
    }

    private static function optional(
        ?string $value,
        int $limit,
    ): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $length = function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

        if ($length > $limit) {
            throw new InvalidArgumentException(
                'Значение федеративной связи слишком длинное.'
            );
        }

        return $value;
    }
}
