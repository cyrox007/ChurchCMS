<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\SecretVault;
use ChurchCMS\Core\Uuid;
use InvalidArgumentException;
use PDO;

final class FederationService
{
    private OrganizationRepository $organizations;
    private FederationRepository $links;

    public function __construct(private readonly PDO $pdo)
    {
        $this->organizations = new OrganizationRepository(
            $pdo,
        );
        $this->links = new FederationRepository(
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

        $localInstanceId = trim(
            (string) Config::get(
                'federation.instance_id',
                '',
            )
        );
        if (
            $localInstanceId !== ''
            && hash_equals(
                strtolower($localInstanceId),
                strtolower($remoteInstanceId),
            )
        ) {
            throw new InvalidArgumentException(
                'Нельзя создать federation link с этой же установкой.'
            );
        }

        $local = $this->organizations->findByPublicId(
            $localOrganizationPublicId,
            $siteKey,
        );
        if ($local === null || $local->status !== 'active') {
            throw new InvalidArgumentException(
                'Активная локальная организация для связи не найдена.'
            );
        }

        $remoteBaseUrl = self::baseUrl(
            $remoteBaseUrl,
        );
        $inboundScopes = self::scopes($inboundScopes);
        $outboundScopes = self::scopes($outboundScopes);

        $encryptedToken = null;
        if (
            $outboundToken !== null
            && trim($outboundToken) !== ''
        ) {
            $outboundToken = trim($outboundToken);
            if (strlen($outboundToken) > 4096) {
                throw new InvalidArgumentException(
                    'Credential federation-связи слишком длинный.'
                );
            }

            $encryptedToken = SecretVault::encrypt(
                $outboundToken,
            );
        }

        $existing = $this->links
            ->findByRemoteInstanceId(
                $remoteInstanceId,
                $siteKey,
            );
        $now = gmdate('Y-m-d H:i:s');

        $values = [
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
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            if ($existing->status !== 'revoked') {
                throw new InvalidArgumentException(
                    'Связь с этим ChurchCMS-узлом уже существует.'
                );
            }

            $statement = $this->pdo->prepare(
                'UPDATE organization_federation_links
                 SET local_organization_id = :local_organization_id,
                     relation = :relation,
                     status = :status,
                     remote_organization_public_id = :remote_organization_public_id,
                     remote_base_url = :remote_base_url,
                     remote_profile = :remote_profile,
                     remote_name = :remote_name,
                     inbound_scopes_json = :inbound_scopes_json,
                     outbound_scopes_json = :outbound_scopes_json,
                     outbound_token_encrypted = :outbound_token_encrypted,
                     sync_cursor = NULL,
                     last_seen_at = NULL,
                     last_error = NULL,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $updateValues = $values;
            unset(
                $updateValues['site_key'],
                $updateValues['remote_instance_id'],
            );

            $statement->execute($updateValues + [
                'id' => $existing->id,
            ]);

            return $existing->publicId;
        }

        $publicId = Uuid::v4();

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
        $statement->execute($values + [
            'public_id' => $publicId,
            'created_at' => $now,
        ]);

        return $publicId;
    }

    public function revoke(
        string $publicId,
        string $siteKey = 'default',
    ): void {
        $publicId = trim($publicId);
        $link = $this->links->findByPublicId(
            $publicId,
            $siteKey,
        );

        if ($link === null) {
            throw new InvalidArgumentException(
                'Federation link не найден.'
            );
        }

        if ($link->status === 'revoked') {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE organization_federation_links
             SET status = :status,
                 outbound_token_encrypted = NULL,
                 sync_cursor = NULL,
                 last_error = NULL,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'status' => 'revoked',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $link->id,
        ]);
    }

    /**
     * Фиксирует результат успешного discovery и проверяет неизменность
     * постоянной identity удалённого узла.
     *
     * @return 'active'|'conflict'
     */
    public function recordHealthSuccess(
        string $publicId,
        string $remoteInstanceId,
        string $remoteOrganizationPublicId,
        ?string $remoteProfile = null,
        ?string $remoteName = null,
        string $siteKey = 'default',
    ): string {
        $link = $this->mutableLink(
            $publicId,
            $siteKey,
        );

        self::assertUuid(
            $remoteInstanceId,
            'remote instance ID',
        );
        self::assertUuid(
            $remoteOrganizationPublicId,
            'remote organization ID',
        );

        $now = gmdate('Y-m-d H:i:s');
        $identityMatches = hash_equals(
            strtolower($link->remoteInstanceId),
            strtolower($remoteInstanceId),
        ) && hash_equals(
            strtolower($link->remoteOrganizationPublicId),
            strtolower($remoteOrganizationPublicId),
        );

        if (!$identityMatches) {
            $statement = $this->pdo->prepare(
                'UPDATE organization_federation_links
                 SET status = :status,
                     last_seen_at = :last_seen_at,
                     last_error = :last_error,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $statement->execute([
                'status' => 'conflict',
                'last_seen_at' => $now,
                'last_error' =>
                    'Удалённый узел вернул другую постоянную identity.',
                'updated_at' => $now,
                'id' => $link->id,
            ]);

            return 'conflict';
        }

        $statement = $this->pdo->prepare(
            'UPDATE organization_federation_links
             SET status = :status,
                 remote_profile = :remote_profile,
                 remote_name = :remote_name,
                 last_seen_at = :last_seen_at,
                 last_error = NULL,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'status' => 'active',
            'remote_profile' => self::optional(
                $remoteProfile,
                64,
            ),
            'remote_name' => self::optional(
                $remoteName,
                255,
            ),
            'last_seen_at' => $now,
            'updated_at' => $now,
            'id' => $link->id,
        ]);

        return 'active';
    }

    /**
     * Фиксирует безопасное сообщение о неуспешной проверке связи.
     * Credential, scopes и sync cursor при этом не изменяются.
     */
    public function recordHealthFailure(
        string $publicId,
        string $message = 'Удалённый узел недоступен или вернул некорректный discovery-ответ.',
        string $siteKey = 'default',
    ): void {
        $link = $this->mutableLink(
            $publicId,
            $siteKey,
        );
        $message = trim($message);

        if ($message === '' || strlen($message) > 500) {
            $message = 'Удалённый узел недоступен или вернул некорректный discovery-ответ.';
        }

        $statement = $this->pdo->prepare(
            'UPDATE organization_federation_links
             SET status = :status,
                 last_error = :last_error,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'status' => 'error',
            'last_error' => $message,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $link->id,
        ]);
    }

    private function mutableLink(
        string $publicId,
        string $siteKey,
    ): FederationLink {
        $link = $this->links->findByPublicId(
            trim($publicId),
            $siteKey,
        );

        if ($link === null) {
            throw new InvalidArgumentException(
                'Federation link не найден.'
            );
        }

        if ($link->status === 'revoked') {
            throw new InvalidArgumentException(
                'Отозванную federation-связь нельзя проверять.'
            );
        }

        return $link;
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
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException(
                'Некорректный адрес удалённого ChurchCMS-узла.'
            );
        }

        if (
            ($parts['scheme'] ?? '') !== 'https'
            && !self::isLocalOrPrivateHost(
                (string) $parts['host'],
            )
        ) {
            throw new InvalidArgumentException(
                'Публичная federation-связь разрешена только по HTTPS.'
            );
        }

        if (strlen($value) > 500) {
            throw new InvalidArgumentException(
                'Адрес удалённого ChurchCMS-узла слишком длинный.'
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
