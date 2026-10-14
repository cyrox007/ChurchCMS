#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', [
    'base:',
    'requests::',
    'concurrency::',
    'warmup::',
    'slug::',
    'comment-every::',
    'comment-slugs::',
]);

$base = rtrim((string) ($options['base'] ?? ''), '/');
$requests = max(3, min(100000, (int) ($options['requests'] ?? 1000)));
$concurrency = max(1, min(500, (int) ($options['concurrency'] ?? 20)));
$warmup = max(0, min(5000, (int) ($options['warmup'] ?? 30)));
$slug = trim((string) ($options['slug'] ?? 'benchmark-000001'));
$commentEvery = max(0, min(100000, (int) ($options['comment-every'] ?? 0)));
$commentSlugs = max(1, min(100000, (int) ($options['comment-slugs'] ?? 100)));

if ($base === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
    fwrite(STDERR, "Укажите --base=http://локальный-хост[:порт] и корректный --slug.\n");
    exit(2);
}

$target = parseTarget($base);
$commentToken = null;

if ($commentEvery > 0) {
    $commentToken = fetchCommentToken($target, $slug);
    if ($commentToken === null) {
        fwrite(STDERR, "Не удалось получить public_form_token для контролируемой отправки комментариев.\n");
        exit(2);
    }
}

if ($warmup > 0) {
    $warmupResult = runScenario(
        target: $target,
        requests: $warmup,
        concurrency: min($concurrency, $warmup),
        slug: $slug,
        commentEvery: 0,
        commentSlugs: $commentSlugs,
        commentToken: null,
    );
    if ($warmupResult['errors'] > 0) {
        fwrite(STDERR, "Прогрев HTTP-сценария завершился ошибками.\n");
        echo json_encode($warmupResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(1);
    }
}

$result = runScenario(
    target: $target,
    requests: $requests,
    concurrency: min($concurrency, $requests),
    slug: $slug,
    commentEvery: $commentEvery,
    commentSlugs: $commentSlugs,
    commentToken: $commentToken,
);
echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
) . PHP_EOL;

exit($result['errors'] === 0 ? 0 : 1);

/**
 * @return array{host:string,port:int,base_path:string,host_header:string,origin:string}
 */
function parseTarget(string $base): array
{
    $parts = parse_url($base);

    if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'http') {
        fwrite(STDERR, "Нагрузочный сценарий без внешних зависимостей работает только через локальный HTTP.\n");
        exit(2);
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!allowedHost($host)) {
        fwrite(
            STDERR,
            "Нагрузочный сценарий разрешён только для localhost, loopback или приватного IPv4-адреса.\n",
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
        'origin' => 'http://' . $hostHeader,
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
 * @param array{host:string,port:int,base_path:string,host_header:string,origin:string} $target
 */
function fetchCommentToken(array $target, string $slug): ?string
{
    $scenario = [
        'label' => 'comment_token_preflight',
        'path' => '/publications/' . rawurlencode($slug),
        'method' => 'GET',
        'body' => '',
        'headers' => [],
        'expected_statuses' => [200],
    ];
    $response = requestSynchronously($target, $scenario);

    if ($response === null || responseStatus($response) !== 200) {
        return null;
    }

    if (preg_match(
        '/name=["\']public_form_token["\'][^>]*value=["\']([^"\']+)["\']/i',
        $response,
        $match,
    ) !== 1) {
        return null;
    }

    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * @param array{host:string,port:int,base_path:string,host_header:string,origin:string} $target
 * @return array<string,mixed>
 */
function runScenario(
    array $target,
    int $requests,
    int $concurrency,
    string $slug,
    int $commentEvery,
    int $commentSlugs,
    ?string $commentToken,
): array {
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
            $scenario = scenarioFor(
                index: $nextRequest,
                slug: $slug,
                commentEvery: $commentEvery,
                commentSlugs: $commentSlugs,
                commentToken: $commentToken,
            );
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

            throw new RuntimeException('Не удалось дождаться ответов нагрузочного HTTP-сценария.');
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

            if (!in_array($status, $connection['scenario']['expected_statuses'], true)) {
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
        'comments_enabled' => $commentEvery > 0,
        'comment_every' => $commentEvery,
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
 * @return array{label:string,path:string,method:string,body:string,headers:list<string>,expected_statuses:list<int>}
 */
function scenarioFor(
    int $index,
    string $slug,
    int $commentEvery,
    int $commentSlugs,
    ?string $commentToken,
): array {
    if ($commentEvery > 0 && $commentToken !== null && ($index + 1) % $commentEvery === 0) {
        return commentScenario($index, $commentEvery, $commentSlugs, $commentToken);
    }

    return match ($index % 10) {
        0, 1 => getScenario('home_cached', '/'),
        2, 3 => getScenario('publications_cached', '/publications'),
        4, 5, 6 => getScenario(
            'publication_detail_cached',
            '/publications/' . rawurlencode($slug),
        ),
        7 => getScenario(
            'publication_detail_cache_miss',
            '/publications/' . rawurlencode($slug) . '?load-test=' . ($index + 1),
        ),
        default => getScenario('public_api', '/api/v1/publications'),
    };
}

/**
 * @return array{label:string,path:string,method:string,body:string,headers:list<string>,expected_statuses:list<int>}
 */
function getScenario(string $label, string $path): array
{
    return [
        'label' => $label,
        'path' => $path,
        'method' => 'GET',
        'body' => '',
        'headers' => [],
        'expected_statuses' => [200],
    ];
}

/**
 * @return array{label:string,path:string,method:string,body:string,headers:list<string>,expected_statuses:list<int>}
 */
function commentScenario(
    int $index,
    int $commentEvery,
    int $commentSlugs,
    string $commentToken,
): array {
    $sequence = intdiv($index + 1, $commentEvery);
    $publicationNumber = (($sequence - 1) % $commentSlugs) + 1;
    $slug = sprintf('benchmark-%06d', $publicationNumber);
    $body = http_build_query([
        'display_name' => 'Нагрузочный тест',
        'email' => '',
        'body_text' => 'Контролируемый комментарий нагрузочного сценария ' . $sequence,
        'public_form_token' => $commentToken,
    ], '', '&', PHP_QUERY_RFC3986);

    return [
        'label' => 'comment_submit',
        'path' => '/publications/' . rawurlencode($slug) . '/comments',
        'method' => 'POST',
        'body' => $body,
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'expected_statuses' => [302, 303],
    ];
}

/**
 * @param array{host:string,port:int,base_path:string,host_header:string,origin:string} $target
 * @param array{label:string,path:string,method:string,body:string,headers:list<string>,expected_statuses:list<int>} $scenario
 * @return array{id:int,stream:resource,started:int,buffer:string,scenario:array}|null
 */
function openRequest(array $target, array $scenario): ?array
{
    $stream = openSocket($target);
    if ($stream === null) {
        return null;
    }

    $started = hrtime(true);
    $request = buildHttpRequest($target, $scenario);
    if (!writeRequest($stream, $request)) {
        fclose($stream);
        return null;
    }

    stream_set_timeout($stream, 10);
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
 * @param array{host:string,port:int,base_path:string,host_header:string,origin:string} $target
 * @param array{label:string,path:string,method:string,body:string,headers:list<string>,expected_statuses:list<int>} $scenario
 */
function requestSynchronously(array $target, array $scenario): ?string
{
    $stream = openSocket($target);
    if ($stream === null) {
        return null;
    }

    if (!writeRequest($stream, buildHttpRequest($target, $scenario))) {
        fclose($stream);
        return null;
    }

    stream_set_timeout($stream, 10);
    $response = stream_get_contents($stream);
    fclose($stream);

    return is_string($response) ? $response : null;
}

/**
 * @param array{host:string,port:int,base_path:string,host_header:string,origin:string} $target
 * @return resource|null
 */
function openSocket(array $target)
{
    $errno = 0;
    $error = '';
    $stream = @stream_socket_client(
        'tcp://' . $target['host'] . ':' . $target['port'],
        $errno,
        $error,
        3,
        STREAM_CLIENT_CONNECT,
    );

    return is_resource($stream) ? $stream : null;
}

/**
 * @param array{host:string,port:int,base_path:string,host_header:string,origin:string} $target
 * @param array{label:string,path:string,method:string,body:string,headers:list<string>,expected_statuses:list<int>} $scenario
 */
function buildHttpRequest(array $target, array $scenario): string
{
    $path = ($target['base_path'] !== '' ? $target['base_path'] : '') . $scenario['path'];
    $headers = [
        $scenario['method'] . ' ' . $path . ' HTTP/1.1',
        'Host: ' . $target['host_header'],
        'User-Agent: ChurchCMS-LoadTest/1',
        'Accept: text/html,application/json;q=0.9,*/*;q=0.1',
        'Origin: ' . $target['origin'],
        'Connection: close',
    ];

    foreach ($scenario['headers'] as $header) {
        $headers[] = $header;
    }

    if ($scenario['body'] !== '') {
        $headers[] = 'Content-Length: ' . strlen($scenario['body']);
    }

    return implode("\r\n", $headers) . "\r\n\r\n" . $scenario['body'];
}

/**
 * @param resource $stream
 */
function writeRequest($stream, string $request): bool
{
    $offset = 0;
    $length = strlen($request);

    while ($offset < $length) {
        $written = fwrite($stream, substr($request, $offset));
        if ($written === false || $written === 0) {
            return false;
        }

        $offset += $written;
    }

    return true;
}

/**
 * @param array<int,array{id:int,stream:resource,started:int,buffer:string,scenario:array}> $active
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
