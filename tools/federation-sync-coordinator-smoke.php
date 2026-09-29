<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\FederationSyncCoordinator;
use ChurchCMS\Modules\Organizations\FederationSyncWorker;

require dirname(__DIR__) . '/core.php';

$ok = new class implements FederationSyncWorker {
    public function id(): string
    {
        return 'publications';
    }

    public function run(
        string $siteKey = 'default',
        int $linkLimit = 20,
        int $pageSize = 100,
    ): array {
        if (
            $siteKey !== 'coordinator-smoke'
            || $linkLimit !== 7
            || $pageSize !== 11
        ) {
            throw new RuntimeException(
                'Координатор потерял параметры запуска.'
            );
        }

        return [
            'links' => 2,
            'succeeded' => 1,
            'failed' => 1,
            'projections' => 3,
            'tombstones' => 1,
            'pending' => 1,
        ];
    }
};

$second = new class implements FederationSyncWorker {
    public function id(): string
    {
        return 'events';
    }

    public function run(
        string $siteKey = 'default',
        int $linkLimit = 20,
        int $pageSize = 100,
    ): array {
        return [
            'links' => 1,
            'succeeded' => 1,
            'failed' => 0,
            'projections' => 2,
            'tombstones' => 0,
            'pending' => 0,
        ];
    }
};

$summary = (new FederationSyncCoordinator([
    $ok,
    $second,
]))->run(
    'coordinator-smoke',
    7,
    11,
);

if (
    $summary['workers'] !== 2
    || $summary['worker_failures'] !== 0
    || $summary['links'] !== 3
    || $summary['succeeded'] !== 2
    || $summary['failed'] !== 1
    || $summary['projections'] !== 5
    || $summary['tombstones'] !== 1
    || $summary['pending'] !== 1
    || !isset($summary['details']['publications'])
    || !isset($summary['details']['events'])
) {
    fwrite(
        STDERR,
        "Координатор неверно суммировал результаты worker'ов.\n",
    );
    exit(1);
}

$failing = new class implements FederationSyncWorker {
    public function id(): string
    {
        return 'documents';
    }

    public function run(
        string $siteKey = 'default',
        int $linkLimit = 20,
        int $pageSize = 100,
    ): array {
        throw new RuntimeException(
            'Ожидаемая smoke-ошибка worker.'
        );
    }
};

$afterFailure = (new FederationSyncCoordinator([
    $failing,
    $second,
]))->run();

if (
    $afterFailure['workers'] !== 2
    || $afterFailure['worker_failures'] !== 1
    || $afterFailure['links'] !== 1
    || $afterFailure['succeeded'] !== 1
    || !isset($afterFailure['details']['documents'])
    || !isset($afterFailure['details']['events'])
) {
    fwrite(
        STDERR,
        "Сбой одного worker остановил или исказил общий federation sync.\n",
    );
    exit(1);
}

try {
    new FederationSyncCoordinator([$ok, $ok]);
    fwrite(
        STDERR,
        "Координатор разрешил два worker с одинаковым ID.\n",
    );
    exit(1);
} catch (RuntimeException) {
}

echo "Общий координатор federation sync проверен\n";
