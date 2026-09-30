<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\FederationLink;
use ChurchCMS\Modules\Organizations\FederationRemoteProjection;
use ChurchCMS\Modules\Organizations\FederationRemoteProjectionRepository;
use ChurchCMS\Modules\Organizations\FederationRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class FederatedMediaFeedService
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
     * Объединяет локально публичные metadata-карточки и active remote
     * projections только от дочерних доверенных узлов.
     *
     * @return list<array<string,mixed>>
     */
    public function latest(
        string $siteKey = 'default',
        int $limit = 20,
    ): array {
        $limit = max(1, min(50, $limit));

        $local = (new MediaRepository($this->pdo))
            ->publicVisible($siteKey, $limit);

        $links = array_values(array_filter(
            (new FederationRepository($this->pdo))
                ->links($siteKey),
            static fn(FederationLink $link): bool =>
                $link->status === 'active'
                && $link->relation === 'child'
                && self::acceptsMedia($link),
        ));

        $linkById = [];
        foreach ($links as $link) {
            $linkById[$link->id] = $link;
        }

        $remote = (new FederationRemoteProjectionRepository(
            $this->pdo,
        ))->activeFromLinks(
            array_keys($linkById),
            'media',
            $limit,
        );

        $items = [];

        foreach ($local as $asset) {
            $items[] = $this->localItem($asset);
        }

        foreach ($remote as $projection) {
            $link = $linkById[$projection->federationLinkId]
                ?? null;
            if (!$link instanceof FederationLink) {
                continue;
            }

            $item = $this->remoteItem($projection, $link);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        usort(
            $items,
            static function (array $left, array $right): int {
                $updated = strcmp(
                    (string) ($right['updated_at'] ?? ''),
                    (string) ($left['updated_at'] ?? ''),
                );
                if ($updated !== 0) {
                    return $updated;
                }

                $source = strcmp(
                    (string) (
                        $left['source']['instance_id']
                        ?? ''
                    ),
                    (string) (
                        $right['source']['instance_id']
                        ?? ''
                    ),
                );
                if ($source !== 0) {
                    return $source;
                }

                return strcmp(
                    (string) $left['id'],
                    (string) $right['id'],
                );
            },
        );

        return array_slice($items, 0, $limit);
    }

    /** @return array<string,mixed> */
    private function localItem(MediaAsset $asset): array
    {
        return [
            'id' => $asset->publicId,
            'type' => 'media',
            'media_type' => $asset->mediaType,
            'mime_type' => $asset->mimeType,
            'bytes' => $asset->bytes,
            'sha256' => $asset->sha256,
            'pixel_width' => $asset->pixelWidth,
            'pixel_height' => $asset->pixelHeight,
            'title' => $asset->title,
            'alt_text' => $asset->altText,
            'blob_available' => false,
            'updated_at' => self::timestamp($asset->updatedAt),
            'url' => null,
            'source' => [
                'kind' => 'local',
                'instance_id' => self::optionalString(
                    Config::get(
                        'federation.instance_id',
                        null,
                    ),
                    36,
                ),
                'organization_id' =>
                    $asset->ownerOrganizationPublicId,
                'name' => (string) Config::get(
                    'site.name',
                    Config::get(
                        'app.name',
                        'ChurchCMS',
                    ),
                ),
                'canonical_url' => null,
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    private function remoteItem(
        FederationRemoteProjection $projection,
        FederationLink $link,
    ): ?array {
        $payload = $projection->payload;
        $mediaType = self::machineKey(
            $payload['media_type'] ?? null,
        );
        $mimeType = self::mimeType(
            $payload['mime_type'] ?? null,
        );
        $bytes = self::nonNegativeInt(
            $payload['bytes'] ?? null,
        );
        $sha256 = self::sha256(
            $payload['sha256'] ?? null,
        );
        $pixelWidth = self::positiveInt(
            $payload['pixel_width'] ?? null,
        );
        $pixelHeight = self::positiveInt(
            $payload['pixel_height'] ?? null,
        );

        if (
            $mediaType === null
            || $mimeType === null
            || $bytes === null
            || $sha256 === null
            || ($payload['blob_available'] ?? false) !== false
        ) {
            return null;
        }

        return [
            'id' => $projection->remotePublicId,
            'type' => 'media',
            'media_type' => $mediaType,
            'mime_type' => $mimeType,
            'bytes' => $bytes,
            'sha256' => $sha256,
            'pixel_width' => $pixelWidth,
            'pixel_height' => $pixelHeight,
            'title' => self::optionalString(
                $payload['title'] ?? null,
                255,
            ),
            'alt_text' => self::optionalString(
                $payload['alt_text'] ?? null,
                500,
            ),
            'blob_available' => false,
            'updated_at' => $projection->remoteUpdatedAt
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(DATE_ATOM),
            'url' => $projection->canonicalUrl,
            'source' => [
                'kind' => 'federation',
                'instance_id' => $link->remoteInstanceId,
                'organization_id' =>
                    $projection
                        ->remoteOwnerOrganizationPublicId,
                'name' => $link->remoteName
                    ?? $link->remoteBaseUrl,
                'canonical_url' =>
                    $projection->canonicalUrl,
            ],
        ];
    }

    private static function acceptsMedia(
        FederationLink $link,
    ): bool {
        return in_array(
            'content.read',
            $link->inboundScopes,
            true,
        );
    }

    private static function timestamp(string $value): string
    {
        try {
            return (new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC'),
            ))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(DATE_ATOM);
        } catch (\Throwable) {
            return '1970-01-01T00:00:00+00:00';
        }
    }

    private static function machineKey(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match(
            '/^[a-z][a-z0-9_.:-]{1,63}$/D',
            $value,
        ) === 1
            ? $value
            : null;
    }

    private static function mimeType(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return preg_match(
            '~^[a-z0-9][a-z0-9!#$&^_.+-]{0,126}/[a-z0-9][a-z0-9!#$&^_.+-]{0,126}$~D',
            $value,
        ) === 1
            ? $value
            : null;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = self::nonNegativeInt($value);

        return $int !== null
            && $int > 0
            && $int <= 1000000
                ? $int
                : null;
    }

    private static function nonNegativeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (
            is_string($value)
            && preg_match('/^[0-9]+$/D', $value) === 1
        ) {
            $int = (int) $value;

            return $int >= 0 ? $int : null;
        }

        return null;
    }

    private static function sha256(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return preg_match('/^[a-f0-9]{64}$/D', $value) === 1
            ? $value
            : null;
    }

    private static function optionalString(
        mixed $value,
        int $maxLength,
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (
            $value === ''
            || self::length($value) > $maxLength
            || preg_match(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                $value,
            ) === 1
        ) {
            return null;
        }

        return $value;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
