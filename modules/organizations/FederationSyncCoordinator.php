<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use RuntimeException;
use Throwable;

final class FederationSyncCoordinator
{
    /** @var list<FederationSyncWorker> */
    private array $workers;

    /**
     * @param list<FederationSyncWorker> $workers
     */
    public function __construct(array $workers)
    {
        $seen = [];

        foreach ($workers as $worker) {
            $id = trim($worker->id());

            if (
                preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $id)
                    !== 1
                || isset($seen[$id])
            ) {
                throw new RuntimeException(
                    'Некорректный или повторяющийся federation sync worker.'
                );
            }

            $seen[$id] = true;
        }

        $this->workers = array_values($workers);
    }

    public static function fromDatabase(): self
    {
        return new self([
            FederationPublicationSyncWorker::fromDatabase(),
            FederationEventSyncWorker::fromDatabase(),
            FederationWorshipSyncWorker::fromDatabase(),
        ]);
    }

    /**
     * Один проход вызывает каждый зарегистрированный worker один раз.
     *
     * @return array{
     *     workers:int,
     *     worker_failures:int,
     *     links:int,
     *     succeeded:int,
     *     failed:int,
     *     projections:int,
     *     tombstones:int,
     *     pending:int,
     *     details:array<string,array<string,int>>
     * }
     */
    public function run(
        string $siteKey = 'default',
        int $linkLimit = 20,
        int $pageSize = 100,
    ): array {
        $summary = [
            'workers' => count($this->workers),
            'worker_failures' => 0,
            'links' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'projections' => 0,
            'tombstones' => 0,
            'pending' => 0,
            'details' => [],
        ];

        foreach ($this->workers as $worker) {
            $id = $worker->id();

            try {
                $result = $worker->run(
                    $siteKey,
                    $linkLimit,
                    $pageSize,
                );
            } catch (Throwable $error) {
                error_log(
                    'ChurchCMS federation sync worker '
                    . $id
                    . ': '
                    . $error->getMessage(),
                );

                $summary['worker_failures']++;
                $summary['details'][$id] = [
                    'links' => 0,
                    'succeeded' => 0,
                    'failed' => 1,
                    'projections' => 0,
                    'tombstones' => 0,
                    'pending' => 0,
                ];
                continue;
            }

            $summary['details'][$id] = $result;

            foreach ([
                'links',
                'succeeded',
                'failed',
                'projections',
                'tombstones',
                'pending',
            ] as $key) {
                $summary[$key] += $result[$key];
            }
        }

        return $summary;
    }
}
