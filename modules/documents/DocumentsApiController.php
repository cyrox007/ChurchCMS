<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\ApiAccess;
use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

final class DocumentsApiController
{
    public function aggregated(Request $request): never
    {
        $limit = max(
            1,
            min(50, (int) $request->get('limit', 20)),
        );
        $items = FederatedDocumentFeedService::fromDatabase()
            ->latest('default', $limit);
        $remote = count(array_filter(
            $items,
            static fn(array $item): bool =>
                ($item['source']['kind'] ?? null)
                === 'federation',
        ));

        ApiResponse::success(
            $items,
            [
                'aggregation' => [
                    'limit' => $limit,
                    'local' => count($items) - $remote,
                    'remote' => $remote,
                ],
            ],
            cacheSeconds: 60,
        );
    }

    public function partnerIndex(Request $request): never
    {
        ApiAccess::requireScope($request, 'content.read');

        [$updatedSince, $updatedSinceRaw, $afterPublicId] =
            $this->cursor(
                $request,
                'invalid_document_cursor',
                'документа',
            );
        $limit = $this->limit($request);

        $documents = DocumentRepository::fromDatabase()
            ->federatedUpdatedSince(
                $updatedSince,
                'default',
                $limit,
                $afterPublicId,
            );

        $items = array_map(
            static fn(DocumentRecord $document): array =>
                (new DocumentApiResource($document))->toApiArray(),
            $documents,
        );

        [$nextUpdatedSince, $nextAfter] =
            $this->nextDocumentCursor($documents);

        ApiResponse::success($items, [
            'sync' => [
                'updated_since' => $updatedSinceRaw !== ''
                    ? $updatedSinceRaw
                    : null,
                'after' => $afterPublicId,
                'next_updated_since' => $nextUpdatedSince,
                'next_after' => $nextAfter,
                'limit' => $limit,
                'has_more' => count($documents) === $limit,
            ],
            'partner' => ApiAccess::partnerId($request),
        ]);
    }

    public function partnerTombstones(Request $request): never
    {
        ApiAccess::requireScope($request, 'content.read');

        [$updatedSince, $updatedSinceRaw, $afterPublicId] =
            $this->cursor(
                $request,
                'invalid_document_tombstone_cursor',
                'tombstone документа',
            );
        $limit = $this->limit($request);

        $tombstones =
            DocumentPartnerTombstoneRepository::fromDatabase()
                ->updatedSince(
                    $updatedSince,
                    'default',
                    $limit,
                    $afterPublicId,
                );

        $items = array_map(
            static fn(array $tombstone): array =>
                (new DocumentPartnerTombstoneApiResource(
                    $tombstone,
                ))->toApiArray(),
            $tombstones,
        );

        $nextUpdatedSince = null;
        $nextAfter = null;

        if ($tombstones !== []) {
            $last = $tombstones[array_key_last($tombstones)];
            $nextUpdatedSince = $last['updated_at']
                ->format(DATE_ATOM);
            $nextAfter = $last['document_public_id'];
        }

        ApiResponse::success($items, [
            'sync' => [
                'updated_since' => $updatedSinceRaw !== ''
                    ? $updatedSinceRaw
                    : null,
                'after' => $afterPublicId,
                'next_updated_since' => $nextUpdatedSince,
                'next_after' => $nextAfter,
                'limit' => $limit,
                'has_more' => count($tombstones) === $limit,
            ],
            'partner' => ApiAccess::partnerId($request),
        ]);
    }

    private function limit(Request $request): int
    {
        return max(
            1,
            min(100, (int) $request->get('limit', 100)),
        );
    }

    /**
     * @return array{0:DateTimeImmutable,1:string,2:?string}
     */
    private function cursor(
        Request $request,
        string $errorCode,
        string $label,
    ): array {
        $updatedSinceRaw = trim(
            (string) $request->get('updated_since', '')
        );
        $afterPublicId = trim(
            (string) $request->get('after', '')
        );
        $afterPublicId = $afterPublicId !== ''
            ? $afterPublicId
            : null;

        if (
            $afterPublicId !== null
            && preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $afterPublicId,
            ) !== 1
        ) {
            ApiResponse::error(
                $errorCode,
                'after должен содержать корректный public ID '
                . $label . '.',
                400,
            );
        }

        if ($updatedSinceRaw === '') {
            return [
                new DateTimeImmutable(
                    '1970-01-01T00:00:00Z',
                ),
                '',
                $afterPublicId,
            ];
        }

        try {
            $updatedSince = new DateTimeImmutable(
                $updatedSinceRaw,
            );
        } catch (Exception) {
            ApiResponse::error(
                'invalid_updated_since',
                'updated_since должен содержать корректное '
                . 'время ISO-8601.',
                400,
            );
        }

        return [
            $updatedSince,
            $updatedSinceRaw,
            $afterPublicId,
        ];
    }

    /**
     * @param list<DocumentRecord> $documents
     * @return array{0:?string,1:?string}
     */
    private function nextDocumentCursor(array $documents): array
    {
        if ($documents === []) {
            return [null, null];
        }

        $last = $documents[array_key_last($documents)];

        return [
            (new DateTimeImmutable(
                $last->updatedAt,
                new DateTimeZone('UTC'),
            ))->format(DATE_ATOM),
            $last->publicId,
        ];
    }
}
