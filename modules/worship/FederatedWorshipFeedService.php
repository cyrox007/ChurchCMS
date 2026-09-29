<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\FederationLink;
use ChurchCMS\Modules\Organizations\FederationRemoteProjection;
use ChurchCMS\Modules\Organizations\FederationRemoteProjectionRepository;
use ChurchCMS\Modules\Organizations\FederationRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class FederatedWorshipFeedService
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
     * Формирует общую ленту ближайших локальных богослужений и active
     * remote projections только от дочерних доверенных узлов.
     *
     * @return list<array<string,mixed>>
     */
    public function upcoming(
        string $siteKey = 'default',
        int $limit = 20,
        ?DateTimeImmutable $from = null,
    ): array {
        $limit = max(1, min(50, $limit));
        $from ??= new DateTimeImmutable(
            'now',
            new DateTimeZone('UTC'),
        );
        $from = $from->setTimezone(
            new DateTimeZone('UTC'),
        );

        $local = (new WorshipRepository($this->pdo))
            ->visibleUpcoming(
                $siteKey,
                $limit,
                $from,
            );

        $links = array_values(array_filter(
            (new FederationRepository($this->pdo))
                ->links($siteKey),
            static fn(FederationLink $link): bool =>
                $link->status === 'active'
                && $link->relation === 'child'
                && self::acceptsWorship($link),
        ));

        $linkById = [];
        foreach ($links as $link) {
            $linkById[$link->id] = $link;
        }

        $remote = (new FederationRemoteProjectionRepository(
            $this->pdo,
        ))->activeFromLinks(
            array_keys($linkById),
            'worship',
            $limit,
        );

        $items = [];

        foreach ($local as $service) {
            $items[] = $this->localItem($service);
        }

        foreach ($remote as $projection) {
            $link = $linkById[$projection->federationLinkId]
                ?? null;
            if (!$link instanceof FederationLink) {
                continue;
            }

            $item = $this->remoteItem(
                $projection,
                $link,
                $from,
            );
            if ($item !== null) {
                $items[] = $item;
            }
        }

        usort(
            $items,
            static function (array $left, array $right): int {
                $time = strcmp(
                    (string) ($left['_sort_at'] ?? ''),
                    (string) ($right['_sort_at'] ?? ''),
                );
                if ($time !== 0) {
                    return $time;
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

        $items = array_slice(
            $items,
            0,
            $limit,
        );

        foreach ($items as &$item) {
            unset($item['_sort_at']);
        }
        unset($item);

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    private function localItem(WorshipService $service): array
    {
        $startsAt = self::timestamp($service->startsAt);

        return [
            'id' => $service->publicId,
            'type' => 'worship',
            'status' => $service->status,
            'title' => $service->title,
            'service_type' => $service->serviceType,
            'starts_at' => $startsAt,
            'ends_at' => self::timestamp(
                $service->endsAt,
            ),
            'location' => $service->locationName,
            'updated_at' => self::timestamp(
                $service->updatedAt,
            ),
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
                    $service->ownerOrganizationPublicId,
                'name' => (string) Config::get(
                    'site.name',
                    Config::get(
                        'app.name',
                        'ChurchCMS',
                    ),
                ),
                'canonical_url' => null,
            ],
            '_sort_at' => $startsAt,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function remoteItem(
        FederationRemoteProjection $projection,
        FederationLink $link,
        DateTimeImmutable $from,
    ): ?array {
        $payload = $projection->payload;
        $status = self::status(
            $payload['status'] ?? null,
        );
        $title = self::optionalString(
            $payload['title'] ?? null,
            255,
        );
        $serviceType = self::machineKey(
            $payload['service_type'] ?? null,
        );
        $startsAt = self::timestamp(
            $payload['starts_at'] ?? null,
        );

        if (
            $status === null
            || $title === null
            || $serviceType === null
            || $startsAt === null
        ) {
            return null;
        }

        if (new DateTimeImmutable($startsAt) < $from) {
            return null;
        }

        return [
            'id' => $projection->remotePublicId,
            'type' => 'worship',
            'status' => $status,
            'title' => $title,
            'service_type' => $serviceType,
            'starts_at' => $startsAt,
            'ends_at' => self::timestamp(
                $payload['ends_at'] ?? null,
            ),
            'location' => self::optionalString(
                $payload['location'] ?? null,
                255,
            ),
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
            '_sort_at' => $startsAt,
        ];
    }

    private static function acceptsWorship(
        FederationLink $link,
    ): bool {
        return in_array(
            'content.read',
            $link->inboundScopes,
            true,
        );
    }

    private static function status(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        return in_array(
            $value,
            ['scheduled', 'cancelled'],
            true,
        )
            ? $value
            : null;
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

    private static function timestamp(
        mixed $value,
    ): ?string {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
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
