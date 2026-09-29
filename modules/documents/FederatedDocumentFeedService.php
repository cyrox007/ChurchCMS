<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\FederationLink;
use ChurchCMS\Modules\Organizations\FederationRemoteProjection;
use ChurchCMS\Modules\Organizations\FederationRemoteProjectionRepository;
use ChurchCMS\Modules\Organizations\FederationRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class FederatedDocumentFeedService
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
     * Объединяет локально публичные документы и активные remote projection
     * только от дочерних доверенных узлов.
     *
     * @return list<array<string,mixed>>
     */
    public function latest(
        string $siteKey = 'default',
        int $limit = 20,
    ): array {
        $limit = max(1, min(50, $limit));

        $local = (new DocumentRepository($this->pdo))
            ->publishedPublic($siteKey, $limit);

        $links = array_values(array_filter(
            (new FederationRepository($this->pdo))
                ->links($siteKey),
            static fn(FederationLink $link): bool =>
                $link->status === 'active'
                && $link->relation === 'child'
                && self::acceptsDocuments($link),
        ));

        $linkById = [];
        foreach ($links as $link) {
            $linkById[$link->id] = $link;
        }

        $remote = (new FederationRemoteProjectionRepository(
            $this->pdo,
        ))->activeFromLinks(
            array_keys($linkById),
            'document',
            $limit,
        );

        $items = [];

        foreach ($local as $document) {
            $items[] = $this->localItem($document);
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
                $date = strcmp(
                    (string) ($right['_sort_at'] ?? ''),
                    (string) ($left['_sort_at'] ?? ''),
                );
                if ($date !== 0) {
                    return $date;
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

        $items = array_slice($items, 0, $limit);

        foreach ($items as &$item) {
            unset($item['_sort_at']);
        }
        unset($item);

        return $items;
    }

    /** @return array<string,mixed> */
    private function localItem(DocumentRecord $document): array
    {
        $updatedAt = self::timestamp($document->updatedAt);
        $sortAt = self::sortTimestamp(
            $document->issuedOn,
            $updatedAt,
        );

        return [
            'id' => $document->publicId,
            'type' => 'document',
            'title' => $document->title,
            'document_type' => $document->documentType,
            'document_number' => $document->documentNumber,
            'issued_on' => $document->issuedOn,
            'summary' => $document->summary,
            'updated_at' => $updatedAt,
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
                    $document->ownerOrganizationPublicId,
                'name' => (string) Config::get(
                    'site.name',
                    Config::get(
                        'app.name',
                        'ChurchCMS',
                    ),
                ),
                'canonical_url' => null,
            ],
            '_sort_at' => $sortAt,
        ];
    }

    /** @return array<string,mixed>|null */
    private function remoteItem(
        FederationRemoteProjection $projection,
        FederationLink $link,
    ): ?array {
        $payload = $projection->payload;
        $title = self::optionalString(
            $payload['title'] ?? null,
            255,
        );
        $documentType = self::machineKey(
            $payload['document_type'] ?? null,
        );

        if ($title === null || $documentType === null) {
            return null;
        }

        $documentNumber = self::optionalString(
            $payload['document_number'] ?? null,
            120,
        );
        $issuedOn = self::date(
            $payload['issued_on'] ?? null,
        );
        $summary = self::optionalString(
            $payload['summary'] ?? null,
            4000,
        ) ?? '';
        $updatedAt = $projection->remoteUpdatedAt
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);

        return [
            'id' => $projection->remotePublicId,
            'type' => 'document',
            'title' => $title,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'issued_on' => $issuedOn,
            'summary' => $summary,
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
            '_sort_at' => self::sortTimestamp(
                $issuedOn,
                $updatedAt,
            ),
        ];
    }

    private static function acceptsDocuments(
        FederationLink $link,
    ): bool {
        return in_array(
            'content.read',
            $link->inboundScopes,
            true,
        );
    }

    private static function timestamp(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return '1970-01-01T00:00:00+00:00';
        }

        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(DATE_ATOM);
        } catch (\Throwable) {
            return '1970-01-01T00:00:00+00:00';
        }
    }

    private static function sortTimestamp(
        ?string $issuedOn,
        string $updatedAt,
    ): string {
        return $issuedOn !== null
            ? $issuedOn . 'T00:00:00+00:00'
            : $updatedAt;
    }

    private static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value,
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    ($errors['warning_count'] ?? 0) > 0
                    || ($errors['error_count'] ?? 0) > 0
                )
            )
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }

        return $value;
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
