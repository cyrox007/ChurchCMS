<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use PDO;

final class DistributionWebhookDeliveryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /** @param array<string,mixed> $payload */
    public function enqueue(
        DistributionWebhookEndpoint $endpoint,
        string $eventType,
        array $payload,
    ): void {
        $eventId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');
        $json = json_encode(
            [
                'id' => $eventId,
                'type' => $eventType,
                'occurred_at' => gmdate(DATE_ATOM),
                'data' => $payload,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $statement = $this->pdo->prepare(
            'INSERT INTO distribution_webhook_deliveries '
            . '(endpoint_id, event_id, event_type, payload_json, status, attempt_count, next_attempt_at, created_at, updated_at) '
            . 'VALUES (:endpoint_id, :event_id, :event_type, :payload_json, :status, 0, :next_attempt_at, :created_at, :updated_at)'
        );
        $statement->execute([
            ':endpoint_id' => $endpoint->id,
            ':event_id' => $eventId,
            ':event_type' => $eventType,
            ':payload_json' => $json,
            ':status' => 'pending',
            ':next_attempt_at' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function due(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->pdo->query(
            'SELECT d.id, d.endpoint_id, d.event_id, d.event_type, d.payload_json, '
            . 'd.status, d.attempt_count, d.next_attempt_at, '
            . 'e.public_id AS endpoint_public_id, e.site_key, e.name AS endpoint_name, '
            . 'e.endpoint_url, e.secret_encrypted, e.event_types, e.is_active '
            . 'FROM distribution_webhook_deliveries d '
            . 'JOIN distribution_webhook_endpoints e ON e.id = d.endpoint_id '
            . "WHERE d.status IN ('pending','retry') "
            . 'AND d.next_attempt_at IS NOT NULL AND d.next_attempt_at <= CURRENT_TIMESTAMP '
            . 'AND e.is_active = 1 '
            . 'ORDER BY d.next_attempt_at, d.id LIMIT ' . $limit
        );
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function delivered(int $id, int $attemptCount, int $responseStatus): void
    {
        $this->complete($id, 'delivered', $attemptCount, null, $responseStatus, null);
    }

    public function retry(
        int $id,
        int $attemptCount,
        string $errorCode,
        ?int $responseStatus,
        int $delaySeconds,
    ): void {
        $next = gmdate('Y-m-d H:i:s', time() + max(60, $delaySeconds));
        $this->complete($id, 'retry', $attemptCount, $next, $responseStatus, $errorCode);
    }

    public function dead(
        int $id,
        int $attemptCount,
        string $errorCode,
        ?int $responseStatus,
    ): void {
        $this->complete($id, 'dead', $attemptCount, null, $responseStatus, $errorCode);
    }

    private function complete(
        int $id,
        string $status,
        int $attemptCount,
        ?string $nextAttemptAt,
        ?int $responseStatus,
        ?string $errorCode,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE distribution_webhook_deliveries SET '
            . 'status = :status, attempt_count = :attempt_count, next_attempt_at = :next_attempt_at, '
            . 'last_attempt_at = :last_attempt_at, response_status = :response_status, '
            . 'last_error_code = :last_error_code, updated_at = :updated_at WHERE id = :id'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            ':status' => $status,
            ':attempt_count' => max(0, $attemptCount),
            ':next_attempt_at' => $nextAttemptAt,
            ':last_attempt_at' => $now,
            ':response_status' => $responseStatus,
            ':last_error_code' => $errorCode,
            ':updated_at' => $now,
            ':id' => $id,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $statement = $this->pdo->query(
            'SELECT d.id, d.event_id, d.event_type, d.status, d.attempt_count, '
            . 'd.next_attempt_at, d.last_attempt_at, d.response_status, d.last_error_code, '
            . 'd.created_at, e.name AS endpoint_name, e.endpoint_url '
            . 'FROM distribution_webhook_deliveries d '
            . 'JOIN distribution_webhook_endpoints e ON e.id = d.endpoint_id '
            . 'ORDER BY d.id DESC LIMIT ' . $limit
        );
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
