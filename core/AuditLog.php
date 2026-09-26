<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use Throwable;

final class AuditLog
{
    /**
     * @param array<string,mixed> $metadata
     */
    public static function emit(
        string $eventType,
        string $severity = 'info',
        ?int $actorUserId = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,99}$/D', $eventType) !== 1) {
            throw new \InvalidArgumentException('Invalid audit event type.');
        }

        if (!in_array($severity, ['info', 'warning', 'error'], true)) {
            throw new \InvalidArgumentException('Invalid audit severity.');
        }

        try {
            $pdo = DatabaseManager::getInstance()->connection();
            $statement = $pdo->prepare(
                'INSERT INTO audit_events (
                    event_type, severity, actor_user_id, subject_type, subject_id,
                    client_ip, user_agent, metadata_json, created_at
                 ) VALUES (
                    :event_type, :severity, :actor_user_id, :subject_type, :subject_id,
                    :client_ip, :user_agent, :metadata_json, :created_at
                 )'
            );

            $statement->execute([
                'event_type' => $eventType,
                'severity' => $severity,
                'actor_user_id' => $actorUserId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'client_ip' => $request?->ip(),
                'user_agent' => self::userAgent($request),
                'metadata_json' => json_encode(
                    self::sanitizeMetadata($metadata),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            error_log(sprintf(
                'ChurchCMS audit fallback: event=%s severity=%s error=%s',
                $eventType,
                $severity,
                $e->getMessage(),
            ));
        }
    }

    private static function userAgent(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $value = trim((string) $request->header('User-Agent', ''));
        return $value === '' ? null : substr($value, 0, 255);
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array<string,mixed>
     */
    private static function sanitizeMetadata(array $metadata): array
    {
        $blockedFragments = [
            'password',
            'passwd',
            'secret',
            'token',
            'authorization',
            'cookie',
            'session',
        ];

        $result = [];
        foreach ($metadata as $key => $value) {
            $normalized = strtolower((string) $key);
            foreach ($blockedFragments as $fragment) {
                if (str_contains($normalized, $fragment)) {
                    continue 2;
                }
            }

            if (is_scalar($value) || $value === null) {
                $result[(string) $key] = $value;
            } elseif (is_array($value)) {
                $result[(string) $key] = self::sanitizeMetadata($value);
            }
        }

        return $result;
    }
}
