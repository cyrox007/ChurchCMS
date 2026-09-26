#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', [
    'base:',
    'requests::',
    'concurrency::',
    'warmup::',
    'slug::',
]);

$base = rtrim((string) ($options['base'] ?? ''), '/');
$requests = max(3, min(100000, (int) ($options['requests'] ?? 1000)));
$concurrency = max(1, min(500, (int) ($options['concurrency'] ?? 20)));
$warmup = max(0, min(5000, (int) ($options['warmup'] ?? 30)));
$slug = trim((string) ($options['slug'] ?? 'benchmark-000001'));

if ($base === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
    fwrite(STDERR, "Укажите --base=http://локальный-хост[:порт] и корректный --slug.\n");
    exit(2);
}

$target = parseTarget($base);

if ($warmup > 0) {
    $warmupResult = runScenario($target, $warmup, min($concurrency, $warmup), $slug);
    if ($warmupResult['errors'] > 0) {
        fwrite(STDERR, "Прогрев HTTP-сценария завершился ошибками.\n");
        echo json_encode($warmupResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(1);
    }
}

$result = runScenario($target, $requests, min($concurrency, $requests), $slug);
echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
) . PHP_EOL;

exit($result['errors'] === 0 ? 0 : 1);

/**
 * @return array{host:string,port:int,base_path:string,host_header:string}
 */
function parseTarget(string $base): array
{
    $parts = parse_url($base);

    if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'http') {
        fwrite(STDERR, "Dependency-free load-сценарий работает только через локальный HTTP.\n");
        exit(2);
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!allowedHost($host)) {
        fwrite(
            STDERR,
            "Load-сценарий разрешён только для localhost, loopback или приватного IPv4-адреса.\n",
        );
        exit(2);
    }

    $port = (int) ($parts['port'] ?? 80);
    if ($port < 1 || $port > 65535) {
        fwrite(STDERR, "Некорректный порт HTTP-цели.\n");
        exit(2);
    }

    $basePath = rtrim((string) ($parts['path'] ?? ''), '/');
    $hostHeader = $host . ($port === 80 ? '' : ':' . $port);

    return [
        'host' => $host,
        'port' => $port,
        'base_path' => $basePath,
        'host_header' => $hostHeader,
    ];
}

function allowedHost(string $host): bool
{
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }

    $parts = array_map('intval', explode('.', $host));

    if ($parts[0] === 10 || ($parts[0] === 192 && $parts[1] === 168)) {
        return true;
    }

    return $parts[0] === 172 && $parts[1] >= 16 && $parts[1] <= 31;
}

/**
 * @param array{host:string,port:int,base_path:string,host_header:string} $target
 * @return array<string,mixed>
 */
function runScenario(array $target, int $requests, int $concurrency, string $slug): array
{
    $started = hrtime(true);
    $nextRequest = 0;
    $completed = 0;
    $errors = 0;
    $active = [];
    $latencies = [];
    $latenciesByScenario = [];
    $statusCounts = [];

    while ($completed < $requests) {
        while (count($active) < $concurrency && $nextRequest < $requests) {
            $scenario = scenarioFor($nextRequest, $slug);
            $connection = openRequest($target, $scenario);

            if ($connection === null) {
                $errors++;
                $completed++;
                $nextRequest++;
                continue;
            }

            $id = (int) $connection['id'];
            $active[$id] = $connection;
            $nextRequest++;
        }

        if ($active === []) {
            continue;
        }

        $read = array_map(
            static fn(array $item) => $item['stream'],
            array_values($active),
        );
        $write = null;
        $except = null;
        $selected = @stream_select($read, $write, $except, 2);

        if ($selected === false) {
            foreach ($active as $connection) {
                fclose($connection['stream']);
            }

            throw new RuntimeException('Не удалось ожидать ответы HTTP-сценария.');
        }

        if ($selected === 0) {
            expireSlowRequests($active, $completed, $errors);
            continue;
        }

        foreach ($read as $stream) {
            $id = (int) $stream;
            if (!isset($active[$id])) {
                continue;
            }

            $chunk = fread($stream, 65536);
            if (is_string($chunk) && $chunk !== '') {
                $active[$id]['buffer'] .= $chunk;
            }

            if (!feof($stream)) {
                continue;
            }

            $connection = $active[$id];
            fclose($stream);
            unset($active[$id]);

            $latencyMs = (hrtime(true) - $connection['started']) / 1_000_000;
            $status = responseStatus($connection['buffer']);
            $label = $connection['scenario']['label'];

            $latencies[] = $latencyMs;
            $latenciesByScenario[$label][] = $latencyMs;
            $statusCounts[(string) $status] = ($statusCounts[(string) $status] ?? 0) + 1;

            if ($status !== 200) {
                $errors++;
            }

            $completed++;
        }
    }

    $elapsedSeconds = max(0.000001, (hrtime(true) - $started) / 1_000_000_000);
    $scenarios = [];

    foreach ($latenciesByScenario as $label => $samples) {
        $scenarios[$label] = latencyStats($samples);
    }

    ksort($statusCounts, SORT_STRING);
    ksort($scenarios, SORT_STRING);

    return [
        'requests' => $requests,
        'concurrency' => $concurrency,
        'errors' => $errors,
        'error_rate' => round($errors / $requests, 6),
        'elapsed_seconds' => round($elapsedSeconds, 3),
        'requests_per_second' => round($requests / $elapsedSeconds, 2),
        'latency' => latencyStats($latencies),
        'scenarios' => $scenarios,
        'statuses' => $statusCounts,
    ];
}

/**
 * @return array{label:string,path:string}
 */
function scenarioFor(int $index, string $slug): array
{
    return match ($index % 10) {
        0, 1 => ['label' => 'home_cached', 'path' => '/'],
        2, 3, 4 => ['label' => 'publications_cached', 'path' => '/publications'],
        5, 6, 7 => [
            'label' => 'publication_detail_cached',
            'path' => '/publications/' . rawurlencode($slug),
        ],
        default => [
            'label' => 'publication_detail_cache_miss',
            'path' => '/publications/' . rawurlencode($slug) . '?load-test=1',
        ],
    };
}

/**
 * @param array{host:string,port:int,base_path:string,host_header:string} $target
 * @param array{label:string,path:string} $scenario
 * @return array{id:int,stream:resource,started:int,buffer:string,scenario:array{label:string,path:string}}|null
 */
function openRequest(array $target, array $scenario): ?array
{
    $errno = 0;
    $error = '';
    $started = hrtime(true);
    $stream = @stream_socket_client(
        'tcp://' . $target['host'] . ':' . $target['port'],
        $errno,
        $error,
        3,
        STREAM_CLIENT_CONNECT,
    );

    if (!is_resource($stream)) {
        return null;
    }

    $path = ($target['base_path'] !== '' ? $target['base_path'] : '') . $scenario['path'];
    $request = "GET {$path} HTTP/1.1\r\n"
        . 'Host: ' . $target['host_header'] . "\r\n"
        . "User-Agent: ChurchCMS-LoadTest/1\r\n"
        . "Accept: text/html,application/json;q=0.9,*/*;q=0.1\r\n"
        . "Connection: close\r\n\r\n";

    stream_set_timeout($stream, 10);

    $offset = 0;
    $length = strlen($request);
    while ($offset < $length) {
        $written = fwrite($stream, substr($request, $offset));
        if ($written === false || $written === 0) {
            fclose($stream);
            return null;
        }

        $offset += $written;
    }

    stream_set_blocking($stream, false);

    return [
        'id' => (int) $stream,
        'stream' => $stream,
        'started' => $started,
        'buffer' => '',
        'scenario' => $scenario,
    ];
}

/**
 * @param array<int,array{id:int,stream:resource,started:int,buffer:string,scenario:array{label:string,path:string}}> $active
 */
function expireSlowRequests(array &$active, int &$completed, int &$errors): void
{
    $now = hrtime(true);
    $timeoutNs = 10_000_000_000;

    foreach ($active as $id => $connection) {
        if ($now - $connection['started'] <= $timeoutNs) {
            continue;
        }

        fclose($connection['stream']);
        unset($active[$id]);
        $completed++;
        $errors++;
    }
}

function responseStatus(string $response): int
{
    if (preg_match('/^HTTP\/1\.[01]\s+([0-9]{3})/D', $response, $match) !== 1) {
        return 0;
    }

    return (int) $match[1];
}

/**
 * @param list<float> $samples
 * @return array{p50_ms:float,p95_ms:float,p99_ms:float,max_ms:float,mean_ms:float}
 */
function latencyStats(array $samples): array
{
    if ($samples === []) {
        return [
            'p50_ms' => 0.0,
            'p95_ms' => 0.0,
            'p99_ms' => 0.0,
            'max_ms' => 0.0,
            'mean_ms' => 0.0,
        ];
    }

    sort($samples, SORT_NUMERIC);
    $count = count($samples);
    $percentile = static function (float $ratio) use ($samples, $count): float {
        $index = (int) ceil($count * $ratio) - 1;
        return $samples[max(0, min($count - 1, $index))];
    };

    return [
        'p50_ms' => round($percentile(0.50), 3),
        'p95_ms' => round($percentile(0.95), 3),
        'p99_ms' => round($percentile(0.99), 3),
        'max_ms' => round(max($samples), 3),
        'mean_ms' => round(array_sum($samples) / $count, 3),
    ];
}
