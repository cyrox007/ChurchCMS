<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class FederationSyncDashboardService
{
    private FederationRepository $links;
    private FederationWorkerSyncStateRepository $states;
    private FederationSyncCoordinator $coordinator;

    public function __construct(
        private readonly PDO $pdo,
        ?FederationSyncCoordinator $coordinator = null,
    ) {
        $this->links = new FederationRepository($pdo);
        $this->states = new FederationWorkerSyncStateRepository(
            $pdo,
        );
        $this->coordinator = $coordinator
            ?? FederationSyncCoordinator::fromDatabase();
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    /**
     * Read-model для Admin Shell. Содержимое sync cursor намеренно
     * наружу не передаётся.
     *
     * @return array{
     *     metrics:array{
     *         links:int,
     *         active_links:int,
     *         workers:int,
     *         successful:int,
     *         failed:int,
     *         partial:int,
     *         not_started:int
     *     },
     *     links:array<int,array{
     *         workers:list<array{
     *             id:string,
     *             status:string,
     *             last_sync_at:?string,
     *             error:?string,
     *             has_cursor:bool
     *         }>
     *     }>
     * }
     */
    public function snapshot(
        string $siteKey = 'default',
    ): array {
        $links = $this->links->links($siteKey);
        $linkIds = array_map(
            static fn(FederationLink $link): int => $link->id,
            $links,
        );
        $states = $this->states->statesForLinks($linkIds);
        $workerIds = $this->coordinator->workerIds();

        $metrics = [
            'links' => count($links),
            'active_links' => 0,
            'workers' => 0,
            'successful' => 0,
            'failed' => 0,
            'partial' => 0,
            'not_started' => 0,
        ];
        $byLink = [];

        foreach ($links as $link) {
            if ($link->status === 'active') {
                $metrics['active_links']++;
            }

            $workers = [];
            foreach ($workerIds as $workerId) {
                if (!self::applies($link, $workerId)) {
                    continue;
                }

                $state = $states[$link->id][$workerId]
                    ?? null;
                $worker = self::workerState(
                    $workerId,
                    $state,
                );

                $workers[] = $worker;
                $metrics['workers']++;
                $metrics[$worker['status']]++;
            }

            $byLink[$link->id] = [
                'workers' => $workers,
            ];
        }

        return [
            'metrics' => $metrics,
            'links' => $byLink,
        ];
    }

    /**
     * @param array{
     *     cursor:?string,
     *     last_sync_at:?string,
     *     last_sync_error:?string
     * }|null $state
     * @return array{
     *     id:string,
     *     status:string,
     *     last_sync_at:?string,
     *     error:?string,
     *     has_cursor:bool
     * }
     */
    private static function workerState(
        string $workerId,
        ?array $state,
    ): array {
        $cursor = $state['cursor'] ?? null;
        $lastSyncAt = $state['last_sync_at'] ?? null;
        $error = $state['last_sync_error'] ?? null;

        $status = 'not_started';
        if ($error !== null) {
            $status = 'failed';
        } elseif ($lastSyncAt !== null) {
            $status = 'successful';
        } elseif ($cursor !== null) {
            $status = 'partial';
        }

        return [
            'id' => $workerId,
            'status' => $status,
            'last_sync_at' => $lastSyncAt,
            'error' => $error,
            'has_cursor' => $cursor !== null,
        ];
    }

    private static function applies(
        FederationLink $link,
        string $workerId,
    ): bool {
        if ($link->status !== 'active') {
            return false;
        }

        if ($workerId === 'publications') {
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

        if (
            in_array(
                $workerId,
                ['events', 'worship', 'documents', 'media'],
                true,
            )
        ) {
            return in_array(
                'content.read',
                $link->inboundScopes,
                true,
            );
        }

        return false;
    }
}
