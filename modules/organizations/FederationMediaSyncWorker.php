<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class FederationMediaSyncWorker implements FederationSyncWorker
{
    private const EPOCH = '1970-01-01T00:00:00+00:00';

    private FederationRepository $links;
    private FederationProjectionService $projections;
    private FederationWorkerSyncStateRepository $syncStates;
    private FederationSyncTransport $transport;

    public function __construct(
        private readonly PDO $pdo,
        ?FederationSyncTransport $transport = null,
    ) {
        $this->links = new FederationRepository($pdo);
        $this->projections = new FederationProjectionService($pdo);
        $this->syncStates = new FederationWorkerSyncStateRepository(
            $pdo,
        );
        $this->transport = $transport
            ?? new FederationHttpSyncTransport();
    }

    public function id(): string
    {
        return 'media';
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    /**
     * Один запуск обрабатывает не более одной страницы медиа
     * и одной страницы tombstone для каждой подходящей связи.
     *
     * @return array{
     *     links:int,
     *     succeeded:int,
     *     failed:int,
     *     projections:int,
     *     tombstones:int,
     *     pending:int
     * }
     */
    public function run(
        string $siteKey = 'default',
        int $linkLimit = 20,
        int $pageSize = 100,
    ): array {
        $linkLimit = max(1, min(100, $linkLimit));
        $pageSize = max(1, min(100, $pageSize));

        $links = array_values(array_filter(
            $this->links->links($siteKey),
            static fn(FederationLink $link): bool =>
                $link->status === 'active'
                && self::acceptsMedia($link),
        ));
        $links = array_slice($links, 0, $linkLimit);

        $result = [
            'links' => count($links),
            'succeeded' => 0,
            'failed' => 0,
            'projections' => 0,
            'tombstones' => 0,
            'pending' => 0,
        ];

        foreach ($links as $link) {
            try {
                $synced = $this->syncLink(
                    $link,
                    $pageSize,
                );
                $result['succeeded']++;
                $result['projections'] +=
                    $synced['projections'];
                $result['tombstones'] +=
                    $synced['tombstones'];
                if ($synced['pending']) {
                    $result['pending']++;
                }
            } catch (Throwable $error) {
                error_log(
                    'ChurchCMS federation sync медиа: '
                    . $error->getMessage()
                );
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * @return array{
     *     projections:int,
     *     tombstones:int,
     *     pending:bool
     * }
     */
    private function syncLink(
        FederationLink $link,
        int $pageSize,
    ): array {
        $storedCursor = $this->syncStates->cursor(
            $link->id,
            $this->id(),
        );

        try {
            return $this->syncLinkPages(
                $link,
                $pageSize,
                $storedCursor,
            );
        } catch (Throwable $error) {
            try {
                $this->syncStates->recordFailure(
                    $link->id,
                    $this->id(),
                    'Синхронизация медиа не выполнена. '
                    . 'Повторите попытку после проверки связи.',
                    $storedCursor,
                );
            } catch (Throwable $storageError) {
                error_log(
                    'ChurchCMS не сохранила ошибку federation sync: '
                    . $storageError->getMessage()
                );
            }

            throw $error;
        }
    }

    /**
     * @return array{
     *     projections:int,
     *     tombstones:int,
     *     pending:bool
     * }
     */
    private function syncLinkPages(
        FederationLink $link,
        int $pageSize,
        ?string &$storedCursor,
    ): array {
        $token = $this->links->outboundToken($link->id);
        if ($token === null) {
            throw new RuntimeException(
                'Для federation sync не сохранён outbound credential.'
            );
        }

        $cursor = self::decodeCursor(
            $storedCursor,
        );
        $projectionCount = 0;
        $tombstoneCount = 0;

        $documentPage = $this->page(
            $link,
            '/api/v1/partner/media',
            $cursor['media'],
            $token,
            $pageSize,
        );

        foreach ($documentPage['items'] as $item) {
            $applied = $this->projections->applyUpsert(
                $link->publicId,
                'media',
                $item,
                $link->siteKey,
            );
            if ($applied !== 'ignored') {
                $projectionCount++;
            }
        }

        $cursor['media'] =
            $documentPage['cursor'];
        $documentCursor = self::encodeCursor($cursor);
        if ($documentCursor !== $storedCursor) {
            $this->syncStates->saveCursor(
                $link->id,
                $this->id(),
                $storedCursor,
                $documentCursor,
            );
            $storedCursor = $documentCursor;
        }

        $tombstonePage = $this->page(
            $link,
            '/api/v1/partner/media/tombstones',
            $cursor['tombstones'],
            $token,
            $pageSize,
        );

        foreach ($tombstonePage['items'] as $item) {
            $applied = $this->projections->applyTombstone(
                $link->publicId,
                'media',
                $item,
                $link->siteKey,
            );
            if ($applied !== 'ignored') {
                $tombstoneCount++;
            }
        }

        $cursor['tombstones'] =
            $tombstonePage['cursor'];
        $encoded = self::encodeCursor($cursor);

        $this->syncStates->recordSuccess(
            $link->id,
            $this->id(),
            $storedCursor,
            $encoded,
        );

        return [
            'projections' => $projectionCount,
            'tombstones' => $tombstoneCount,
            'pending' => $documentPage['has_more']
                || $tombstonePage['has_more'],
        ];
    }

    /**
     * @param array{updated_since:string,after:?string} $cursor
     * @return array{
     *     items:list<array<string,mixed>>,
     *     cursor:array{updated_since:string,after:?string},
     *     has_more:bool
     * }
     */
    private function page(
        FederationLink $link,
        string $path,
        array $cursor,
        string $token,
        int $pageSize,
    ): array {
        $query = [
            'updated_since' => $cursor['updated_since'],
            'limit' => $pageSize,
        ];
        if ($cursor['after'] !== null) {
            $query['after'] = $cursor['after'];
        }

        $payload = $this->transport->getJson(
            $link->remoteBaseUrl,
            $path,
            $query,
            $token,
        );

        $items = $payload['data'] ?? null;
        $meta = $payload['meta'] ?? null;
        $sync = is_array($meta)
            ? ($meta['sync'] ?? null)
            : null;

        if (!is_array($items) || !is_array($sync)) {
            throw new RuntimeException(
                'Partner API вернул ответ без data/meta.sync.'
            );
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new RuntimeException(
                    'Partner API вернул некорректный элемент синхронизации.'
                );
            }

            $normalized[] = $item;
        }

        $nextCursor = self::nextCursor(
            $cursor,
            $sync,
        );
        $hasMore = ($sync['has_more'] ?? false) === true;

        if (
            ($normalized !== [] || $hasMore)
            && $nextCursor === $cursor
        ) {
            throw new RuntimeException(
                'Partner API вернул данные без продвижения курсора.'
            );
        }

        return [
            'items' => $normalized,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * @param array{updated_since:string,after:?string} $current
     * @param array<string,mixed> $sync
     * @return array{updated_since:string,after:?string}
     */
    private static function nextCursor(
        array $current,
        array $sync,
    ): array {
        $nextUpdated = $sync['next_updated_since']
            ?? null;
        $nextAfter = $sync['next_after']
            ?? null;

        if ($nextUpdated === null && $nextAfter === null) {
            return $current;
        }

        if (
            !is_string($nextUpdated)
            || !is_string($nextAfter)
        ) {
            throw new RuntimeException(
                'Partner API вернул неполный следующий курсор.'
            );
        }

        $next = [
            'updated_since' => self::timestamp(
                $nextUpdated,
            ),
            'after' => self::publicId($nextAfter),
        ];

        self::assertCursorForward(
            $current,
            $next,
        );

        return $next;
    }

    /**
     * @param array{updated_since:string,after:?string} $current
     * @param array{updated_since:string,after:?string} $next
     */
    private static function assertCursorForward(
        array $current,
        array $next,
    ): void {
        $currentTime = new DateTimeImmutable(
            $current['updated_since'],
        );
        $nextTime = new DateTimeImmutable(
            $next['updated_since'],
        );

        if ($nextTime < $currentTime) {
            throw new RuntimeException(
                'Partner API попытался сдвинуть курсор назад.'
            );
        }

        if ($nextTime > $currentTime) {
            return;
        }

        $currentAfter = $current['after'];
        if (
            $currentAfter !== null
            && strcmp(
                (string) $next['after'],
                $currentAfter,
            ) <= 0
        ) {
            throw new RuntimeException(
                'Partner API не продвинул составной курсор.'
            );
        }
    }

    /**
     * @return array{
     *     media:array{updated_since:string,after:?string},
     *     tombstones:array{updated_since:string,after:?string}
     * }
     */
    private static function decodeCursor(
        ?string $raw,
    ): array {
        if ($raw === null || trim($raw) === '') {
            return self::emptyCursor();
        }

        $raw = trim($raw);
        if (!str_starts_with($raw, '{')) {
            $legacy = self::timestamp($raw);

            return [
                'media' => [
                    'updated_since' => $legacy,
                    'after' => null,
                ],
                'tombstones' => [
                    'updated_since' => $legacy,
                    'after' => null,
                ],
            ];
        }

        try {
            $decoded = json_decode(
                $raw,
                true,
                32,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $error) {
            throw new RuntimeException(
                'Повреждён курсор federation sync.',
                0,
                $error,
            );
        }

        if (
            !is_array($decoded)
            || ($decoded['version'] ?? null) !== 1
        ) {
            throw new RuntimeException(
                'Неподдерживаемый формат курсора federation sync.'
            );
        }

        return [
            'media' => self::streamCursor(
                $decoded['media'] ?? null,
            ),
            'tombstones' => self::streamCursor(
                $decoded['tombstones'] ?? null,
            ),
        ];
    }

    /**
     * @param array{
     *     media:array{updated_since:string,after:?string},
     *     tombstones:array{updated_since:string,after:?string}
     * } $cursor
     */
    private static function encodeCursor(
        array $cursor,
    ): string {
        return json_encode(
            [
                'version' => 1,
                'media' => $cursor['media'],
                'tombstones' => $cursor['tombstones'],
            ],
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @return array{
     *     media:array{updated_since:string,after:?string},
     *     tombstones:array{updated_since:string,after:?string}
     * }
     */
    private static function emptyCursor(): array
    {
        return [
            'media' => [
                'updated_since' => self::EPOCH,
                'after' => null,
            ],
            'tombstones' => [
                'updated_since' => self::EPOCH,
                'after' => null,
            ],
        ];
    }

    /**
     * @return array{updated_since:string,after:?string}
     */
    private static function streamCursor(
        mixed $value,
    ): array {
        if (!is_array($value)) {
            throw new RuntimeException(
                'В курсоре federation sync отсутствует поток.'
            );
        }

        $after = $value['after'] ?? null;
        if ($after !== null && !is_string($after)) {
            throw new RuntimeException(
                'Некорректный public ID в курсоре federation sync.'
            );
        }

        return [
            'updated_since' => self::timestamp(
                (string) ($value['updated_since'] ?? ''),
            ),
            'after' => $after !== null
                ? self::publicId($after)
                : null,
        ];
    }

    private static function timestamp(
        string $value,
    ): string {
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException(
                'В курсоре federation sync отсутствует время.'
            );
        }

        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(DATE_ATOM);
        } catch (\Exception $error) {
            throw new RuntimeException(
                'Некорректное время курсора federation sync.',
                0,
                $error,
            );
        }
    }

    private static function publicId(
        string $value,
    ): string {
        $value = strtolower(trim($value));
        if (
            preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',
                $value,
            ) !== 1
        ) {
            throw new RuntimeException(
                'Некорректный public ID курсора federation sync.'
            );
        }

        return $value;
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
}
