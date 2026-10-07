<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

use ChurchCMS\Core\SecretVault;
use Throwable;

final class DistributionWebhookWorker
{
    private const MAX_ATTEMPTS = 5;
    private const RETRY_DELAYS = [60, 300, 1800, 7200, 21600];

    public function __construct(
        private readonly DistributionWebhookDeliveryRepository $deliveries,
        private readonly DistributionWebhookTransport $transport,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DistributionWebhookDeliveryRepository::fromDatabase(),
            new DistributionWebhookHttpClient(),
        );
    }

    /** @return array{processed:int,delivered:int,retried:int,dead:int} */
    public function run(int $limit = 25): array
    {
        $stats = [
            'processed' => 0,
            'delivered' => 0,
            'retried' => 0,
            'dead' => 0,
        ];

        foreach ($this->deliveries->due($limit) as $delivery) {
            $id = (int) ($delivery['id'] ?? 0);
            $expectedAttemptCount = (int) ($delivery['attempt_count'] ?? 0);
            if (!$this->deliveries->claim($id, $expectedAttemptCount)) {
                continue;
            }

            ++$stats['processed'];
            $result = $this->deliver(
                $delivery,
                $expectedAttemptCount + 1,
            );
            ++$stats[$result];
        }

        return $stats;
    }

    /** @param array<string,mixed> $delivery */
    private function deliver(array $delivery, int $attempt): string
    {
        $id = (int) ($delivery['id'] ?? 0);
        $payload = (string) ($delivery['payload_json'] ?? '');
        $eventId = (string) ($delivery['event_id'] ?? '');
        $eventType = (string) ($delivery['event_type'] ?? '');
        $endpointUrl = (string) ($delivery['endpoint_url'] ?? '');
        $timestamp = (string) time();

        try {
            $secret = SecretVault::decrypt(
                (string) ($delivery['secret_encrypted'] ?? ''),
            );
            $response = $this->transport->post(
                $endpointUrl,
                $payload,
                [
                    'X-ChurchCMS-Event' => $eventType,
                    'X-ChurchCMS-Event-Id' => $eventId,
                    'X-ChurchCMS-Timestamp' => $timestamp,
                    'X-ChurchCMS-Signature' => DistributionWebhookSignature::sign(
                        $secret,
                        $timestamp,
                        $payload,
                    ),
                ],
            );

            $status = (int) ($response['status'] ?? 0);
            if ($status >= 200 && $status < 300) {
                $this->deliveries->delivered($id, $attempt, $status);
                return 'delivered';
            }

            if ($this->retryableStatus($status) && $attempt < self::MAX_ATTEMPTS) {
                $this->deliveries->retry(
                    $id,
                    $attempt,
                    'http_' . $status,
                    $status > 0 ? $status : null,
                    $this->retryDelay($attempt),
                );
                return 'retried';
            }

            $this->deliveries->dead(
                $id,
                $attempt,
                $status > 0 ? 'http_' . $status : 'http_invalid_status',
                $status > 0 ? $status : null,
            );
            return 'dead';
        } catch (Throwable $error) {
            if ($attempt < self::MAX_ATTEMPTS) {
                $this->deliveries->retry(
                    $id,
                    $attempt,
                    self::errorCode($error),
                    null,
                    $this->retryDelay($attempt),
                );
                return 'retried';
            }

            $this->deliveries->dead(
                $id,
                $attempt,
                self::errorCode($error),
                null,
            );
            return 'dead';
        }
    }

    private function retryableStatus(int $status): bool
    {
        return in_array($status, [408, 425, 429], true)
            || $status >= 500;
    }

    private function retryDelay(int $attempt): int
    {
        $index = max(0, min(count(self::RETRY_DELAYS) - 1, $attempt - 1));
        return self::RETRY_DELAYS[$index];
    }

    private static function errorCode(Throwable $error): string
    {
        $class = strtolower((new \ReflectionClass($error))->getShortName());
        $class = preg_replace('/[^a-z0-9_-]+/', '_', $class) ?? 'error';
        return substr('transport_' . trim($class, '_'), 0, 64);
    }
}
