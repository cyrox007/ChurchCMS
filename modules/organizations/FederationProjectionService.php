<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use PDO;

final class FederationProjectionService
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
     * Применяет полную remote projection только от активной доверенной связи.
     *
     * @param array<string,mixed> $payload
     * @return 'created'|'updated'|'ignored'
     */
    public function applyUpsert(
        string $linkPublicId,
        string $objectType,
        array $payload,
        string $siteKey = 'default',
    ): string {
        $link = $this->activeLink(
            $linkPublicId,
            $siteKey,
        );
        $objectType = self::objectType($objectType);
        $remotePublicId = self::remotePublicId(
            $payload['id'] ?? null,
        );
        $ownerPublicId = self::ownerPublicId(
            $payload['organization_owner_id'] ?? null,
        );
        $canonicalUrl = self::canonicalUrl(
            $payload['url'] ?? null,
        );
        $updatedAt = self::remoteTimestamp(
            $payload['updated_at'] ?? null,
        );

        return (new FederationRemoteProjectionRepository(
            $this->pdo,
        ))->upsert(
            federationLinkId: $link->id,
            objectType: $objectType,
            remotePublicId: $remotePublicId,
            remoteOwnerOrganizationPublicId: $ownerPublicId,
            canonicalUrl: $canonicalUrl,
            remoteUpdatedAt: $updatedAt,
            payload: $payload,
        );
    }

    /**
     * @param array<string,mixed> $tombstone
     * @return 'created'|'updated'|'ignored'
     */
    public function applyTombstone(
        string $linkPublicId,
        string $objectType,
        array $tombstone,
        string $siteKey = 'default',
    ): string {
        $link = $this->activeLink(
            $linkPublicId,
            $siteKey,
        );
        $objectType = self::objectType($objectType);

        if (($tombstone['action'] ?? null) !== 'delete') {
            throw new InvalidArgumentException(
                'Remote tombstone должен иметь action=delete.'
            );
        }

        $remotePublicId = self::remotePublicId(
            $tombstone['id'] ?? null,
        );
        $ownerPublicId = self::ownerPublicId(
            $tombstone['organization_owner_id'] ?? null,
        );
        $updatedAt = self::remoteTimestamp(
            $tombstone['updated_at'] ?? null,
        );
        $reason = self::deleteReason(
            $tombstone['reason'] ?? 'withdrawn',
        );

        return (new FederationRemoteProjectionRepository(
            $this->pdo,
        ))->tombstone(
            federationLinkId: $link->id,
            objectType: $objectType,
            remotePublicId: $remotePublicId,
            remoteOwnerOrganizationPublicId: $ownerPublicId,
            remoteUpdatedAt: $updatedAt,
            reason: $reason,
        );
    }

    private function activeLink(
        string $publicId,
        string $siteKey,
    ): FederationLink {
        $publicId = trim($publicId);
        $siteKey = trim($siteKey);

        if (
            preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $publicId,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный public ID federation link.'
            );
        }

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9_.-]{0,63}$/D',
                $siteKey,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный site key.'
            );
        }

        $link = (new FederationRepository($this->pdo))
            ->findByPublicId(
                $publicId,
                $siteKey,
            );

        if ($link === null) {
            throw new InvalidArgumentException(
                'Federation link не найден.'
            );
        }

        if ($link->status !== 'active') {
            throw new InvalidArgumentException(
                'Remote projection можно принимать только от активной связи.'
            );
        }

        return $link;
    }

    private static function objectType(
        string $value,
    ): string {
        $value = trim($value);

        if (
            preg_match(
                '/^[a-z][a-z0-9_.:-]{1,63}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный тип remote projection.'
            );
        }

        return $value;
    }

    private static function remotePublicId(
        mixed $value,
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Remote projection не содержит public ID.'
            );
        }

        $value = trim($value);

        if (
            $value === ''
            || strlen($value) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный remote public ID.'
            );
        }

        return $value;
    }

    private static function ownerPublicId(
        mixed $value,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (
            !is_string($value)
            || preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный remote organization owner ID.'
            );
        }

        return strtolower($value);
    }

    private static function canonicalUrl(
        mixed $value,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Некорректный canonical URL remote projection.'
            );
        }

        $value = trim($value);
        if ($value === '' || strlen($value) > 1000) {
            throw new InvalidArgumentException(
                'Некорректный canonical URL remote projection.'
            );
        }

        $parts = parse_url($value);
        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(
                strtolower((string) $parts['scheme']),
                ['http', 'https'],
                true,
            )
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException(
                'Canonical URL remote projection должен быть HTTP(S) URL без credentials.'
            );
        }

        return $value;
    }

    private static function remoteTimestamp(
        mixed $value,
    ): DateTimeImmutable {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                'Remote projection не содержит updated_at.'
            );
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $error) {
            throw new InvalidArgumentException(
                'Некорректный updated_at remote projection.',
                0,
                $error,
            );
        }
    }

    private static function deleteReason(
        mixed $value,
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Некорректная причина remote tombstone.'
            );
        }

        $value = trim($value);
        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,31}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректная причина remote tombstone.'
            );
        }

        return $value;
    }
}
