<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\SeoRenderer;
use ChurchCMS\Modules\Organizations\FederationLink;
use ChurchCMS\Modules\Organizations\FederationRemoteProjection;
use ChurchCMS\Modules\Organizations\FederationRemoteProjectionRepository;
use ChurchCMS\Modules\Organizations\FederationRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class FederatedPublicationFeedService
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
     * Формирует общую публичную ленту локальных публикаций и активных
     * remote projections от нижестоящих ChurchCMS-узлов.
     *
     * @return list<array{
     *     id:string,
     *     type:?string,
     *     title:string,
     *     excerpt:string,
     *     author:?string,
     *     published_at:?string,
     *     updated_at:string,
     *     url:?string,
     *     source:array{
     *         kind:string,
     *         instance_id:?string,
     *         organization_id:?string,
     *         name:string,
     *         canonical_url:?string
     *     }
     * }>
     */
    public function latest(
        string $siteKey = 'default',
        int $limit = 20,
    ): array {
        $limit = max(1, min(50, $limit));

        $local = (new PublicationRepository($this->pdo))
            ->published(
                $siteKey,
                $limit,
                0,
            );

        $links = array_values(array_filter(
            (new FederationRepository($this->pdo))
                ->links($siteKey),
            static fn(FederationLink $link): bool =>
                $link->status === 'active'
                && $link->relation === 'child'
                && self::acceptsPublications($link),
        ));

        $linkById = [];
        foreach ($links as $link) {
            $linkById[$link->id] = $link;
        }

        $remote = (new FederationRemoteProjectionRepository(
            $this->pdo,
        ))->activeFromLinks(
            array_keys($linkById),
            'publication',
            $limit,
        );

        $items = [];

        foreach ($local as $publication) {
            $items[] = $this->localItem($publication);
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
            );
            if ($item !== null) {
                $items[] = $item;
            }
        }

        usort(
            $items,
            static function (array $left, array $right): int {
                $time = strcmp(
                    (string) ($right['_sort_at'] ?? ''),
                    (string) ($left['_sort_at'] ?? ''),
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
    private function localItem(Publication $publication): array
    {
        $publishedAt = $publication->publishedAt
            ?->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
        $updatedAt = $publication->updatedAt
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
        $url = self::optionalString(
            SeoRenderer::absoluteUrl(
                '/publications/'
                . rawurlencode($publication->slug),
            ),
            1000,
        );

        return [
            'id' => $publication->publicId,
            'type' => $publication->type->value,
            'title' => $publication->title,
            'excerpt' => $publication->excerpt,
            'author' => $publication->authorName,
            'published_at' => $publishedAt,
            'updated_at' => $updatedAt,
            'url' => $url,
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
                    $publication->ownerOrganizationPublicId,
                'name' => (string) Config::get(
                    'site.name',
                    Config::get(
                        'app.name',
                        'ChurchCMS',
                    ),
                ),
                'canonical_url' => $url,
            ],
            '_sort_at' => $publishedAt ?? $updatedAt,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function remoteItem(
        FederationRemoteProjection $projection,
        FederationLink $link,
    ): ?array {
        $payload = $projection->payload;
        $title = self::optionalString(
            $payload['title'] ?? null,
            255,
        );

        if ($title === null) {
            return null;
        }

        $type = self::optionalToken(
            $payload['type'] ?? null,
        );
        $excerpt = self::optionalString(
            $payload['excerpt'] ?? null,
            10000,
        ) ?? '';
        $author = self::optionalString(
            $payload['author'] ?? null,
            255,
        );
        $publishedAt = self::timestamp(
            $payload['published_at'] ?? null,
        );
        if (
            $publishedAt === null
            || new DateTimeImmutable($publishedAt)
                > new DateTimeImmutable(
                    'now',
                    new DateTimeZone('UTC'),
                )
        ) {
            return null;
        }

        $updatedAt = $projection->remoteUpdatedAt
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);

        return [
            'id' => $projection->remotePublicId,
            'type' => $type,
            'title' => $title,
            'excerpt' => $excerpt,
            'author' => $author,
            'published_at' => $publishedAt,
            'updated_at' => $updatedAt,
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
            '_sort_at' => $publishedAt ?? $updatedAt,
        ];
    }

    private static function acceptsPublications(
        FederationLink $link,
    ): bool {
        return in_array(
            'content.read',
            $link->inboundScopes,
            true,
        ) || in_array(
            'publications.read',
            $link->inboundScopes,
            true,
        );
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
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
                === 1
        ) {
            return null;
        }

        return $value;
    }

    private static function optionalToken(mixed $value): ?string
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

    private static function timestamp(mixed $value): ?string
    {
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

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
