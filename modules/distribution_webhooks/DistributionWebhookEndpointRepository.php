<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\SecretVault;
use ChurchCMS\Core\Uuid;
use InvalidArgumentException;
use PDO;

final class DistributionWebhookEndpointRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /** @param list<string> $eventTypes */
    public function create(
        string $siteKey,
        string $name,
        string $endpointUrl,
        string $secret,
        array $eventTypes,
    ): string {
        $siteKey = $this->siteKey($siteKey);
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 160) {
            throw new InvalidArgumentException('Укажите название webhook endpoint длиной до 160 символов.');
        }
        $endpointUrl = $this->endpointUrl($endpointUrl);
        $secret = trim($secret);
        if (strlen($secret) < 24 || strlen($secret) > 512) {
            throw new InvalidArgumentException('Webhook secret должен содержать от 24 до 512 символов.');
        }
        $eventTypes = $this->eventTypes($eventTypes);

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO distribution_webhook_endpoints '
            . '(public_id, site_key, name, endpoint_url, secret_encrypted, event_types, is_active, created_at, updated_at) '
            . 'VALUES (:public_id, :site_key, :name, :endpoint_url, :secret_encrypted, :event_types, 1, :created_at, :updated_at)'
        );
        $statement->execute([
            ':public_id' => $publicId,
            ':site_key' => $siteKey,
            ':name' => $name,
            ':endpoint_url' => $endpointUrl,
            ':secret_encrypted' => SecretVault::encrypt($secret),
            ':event_types' => json_encode($eventTypes, JSON_THROW_ON_ERROR),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return $publicId;
    }

    /** @return list<DistributionWebhookEndpoint> */
    public function activeForEvent(string $siteKey, string $eventType): array
    {
        $siteKey = $this->siteKey($siteKey);
        $eventType = $this->eventType($eventType);
        $statement = $this->pdo->prepare(
            'SELECT id, public_id, site_key, name, endpoint_url, secret_encrypted, event_types, is_active '
            . 'FROM distribution_webhook_endpoints '
            . 'WHERE site_key = :site_key AND is_active = 1 ORDER BY id'
        );
        $statement->execute([':site_key' => $siteKey]);

        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $endpoint = $this->hydrate($row);
            if ($endpoint->accepts($eventType)) {
                $result[] = $endpoint;
            }
        }

        return $result;
    }

    /** @return list<DistributionWebhookEndpoint> */
    public function all(string $siteKey): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, public_id, site_key, name, endpoint_url, secret_encrypted, event_types, is_active '
            . 'FROM distribution_webhook_endpoints WHERE site_key = :site_key ORDER BY id DESC'
        );
        $statement->execute([':site_key' => $this->siteKey($siteKey)]);
        return array_map(fn(array $row): DistributionWebhookEndpoint => $this->hydrate($row), $statement->fetchAll());
    }

    public function setActive(string $publicId, bool $active): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE distribution_webhook_endpoints SET is_active = :active, updated_at = :updated_at '
            . 'WHERE public_id = :public_id'
        );
        $statement->execute([
            ':active' => $active ? 1 : 0,
            ':updated_at' => gmdate('Y-m-d H:i:s'),
            ':public_id' => $publicId,
        ]);
    }

    public function decryptedSecret(DistributionWebhookEndpoint $endpoint): string
    {
        return SecretVault::decrypt($endpoint->secretEncrypted);
    }

    private function hydrate(array $row): DistributionWebhookEndpoint
    {
        $events = json_decode((string) $row['event_types'], true, 32, JSON_THROW_ON_ERROR);
        return new DistributionWebhookEndpoint(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            name: (string) $row['name'],
            endpointUrl: (string) $row['endpoint_url'],
            secretEncrypted: (string) $row['secret_encrypted'],
            eventTypes: is_array($events) ? array_values(array_filter($events, 'is_string')) : [],
            active: (bool) $row['is_active'],
        );
    }

    private function endpointUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Укажите корректный webhook URL.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new InvalidArgumentException('Webhook URL должен использовать HTTPS.');
        }
        if (($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '' || ($parts['fragment'] ?? '') !== '') {
            throw new InvalidArgumentException('Webhook URL не должен содержать credentials или fragment.');
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('Локальный webhook URL запрещён.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException('Приватный или reserved IP запрещён для webhook.');
        }

        return $url;
    }

    /** @param list<string> $eventTypes @return list<string> */
    private function eventTypes(array $eventTypes): array
    {
        $allowed = ['publication.published', 'publication.withdrawn'];
        $result = [];
        foreach ($eventTypes as $eventType) {
            if (!is_string($eventType) || !in_array($eventType, $allowed, true)) {
                throw new InvalidArgumentException('Некорректный тип webhook-события.');
            }
            $result[$eventType] = true;
        }
        if ($result === []) {
            throw new InvalidArgumentException('Выберите хотя бы один тип webhook-события.');
        }
        return array_keys($result);
    }

    private function eventType(string $eventType): string
    {
        if (!in_array($eventType, ['publication.published', 'publication.withdrawn'], true)) {
            throw new InvalidArgumentException('Некорректный тип webhook-события.');
        }
        return $eventType;
    }

    private function siteKey(string $siteKey): string
    {
        $siteKey = trim($siteKey);
        if (preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $siteKey) !== 1) {
            throw new InvalidArgumentException('Некорректный site key.');
        }
        return $siteKey;
    }
}
