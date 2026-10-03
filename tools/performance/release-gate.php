#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', [
    'result:',
    'thresholds:',
]);

$resultPath = trim((string) ($options['result'] ?? ''));
$thresholdsPath = trim((string) ($options['thresholds'] ?? ''));

if ($resultPath === '' || $thresholdsPath === '') {
    fwrite(
        STDERR,
        "Использование: php tools/performance/release-gate.php --result=/path/http-load.json --thresholds=/path/thresholds.json\n",
    );
    exit(2);
}

$result = readJsonFile($resultPath, 'результат HTTP load-test');
$thresholds = readJsonFile($thresholdsPath, 'профиль порогов');

$profile = trim((string) ($thresholds['profile'] ?? ''));
$hardware = $thresholds['reference_hardware'] ?? null;
$limits = $thresholds['limits'] ?? null;

if ($profile === '' || !is_array($hardware) || !is_array($limits)) {
    fwrite(
        STDERR,
        "Профиль порогов должен содержать profile, reference_hardware и limits.\n",
    );
    exit(2);
}

$requiredHardware = [
    'cpu',
    'memory_mb',
    'storage',
    'web_server',
    'php',
    'database',
];

foreach ($requiredHardware as $field) {
    $value = $hardware[$field] ?? null;
    if (
        (!is_string($value) || trim($value) === '')
        && (!is_int($value) || $value <= 0)
    ) {
        fwrite(
            STDERR,
            "В reference_hardware не заполнено обязательное поле {$field}.\n",
        );
        exit(2);
    }
}

$checks = [
    'errors' => [
        'actual' => numericValue($result, ['errors']),
        'limit' => numericValue($limits, ['max_errors']),
        'operator' => '<=',
    ],
    'error_rate' => [
        'actual' => numericValue($result, ['error_rate']),
        'limit' => numericValue($limits, ['max_error_rate']),
        'operator' => '<=',
    ],
    'requests_per_second' => [
        'actual' => numericValue($result, ['requests_per_second']),
        'limit' => numericValue($limits, ['min_requests_per_second']),
        'operator' => '>=',
    ],
    'latency_p50_ms' => [
        'actual' => numericValue($result, ['latency', 'p50_ms']),
        'limit' => numericValue($limits, ['max_latency_p50_ms']),
        'operator' => '<=',
    ],
    'latency_p95_ms' => [
        'actual' => numericValue($result, ['latency', 'p95_ms']),
        'limit' => numericValue($limits, ['max_latency_p95_ms']),
        'operator' => '<=',
    ],
    'latency_p99_ms' => [
        'actual' => numericValue($result, ['latency', 'p99_ms']),
        'limit' => numericValue($limits, ['max_latency_p99_ms']),
        'operator' => '<=',
    ],
];

$scenarioLimits = $limits['scenarios'] ?? [];
if (!is_array($scenarioLimits)) {
    fwrite(STDERR, "limits.scenarios должен быть объектом.\n");
    exit(2);
}

foreach ($scenarioLimits as $scenario => $scenarioLimit) {
    if (!is_string($scenario) || !is_array($scenarioLimit)) {
        fwrite(STDERR, "Некорректный сценарий в профиле порогов.\n");
        exit(2);
    }

    foreach (['p50_ms', 'p95_ms', 'p99_ms'] as $metric) {
        $limitKey = 'max_' . $metric;
        if (!array_key_exists($limitKey, $scenarioLimit)) {
            continue;
        }

        $checks['scenario.' . $scenario . '.' . $metric] = [
            'actual' => numericValue(
                $result,
                ['scenarios', $scenario, $metric],
            ),
            'limit' => numericValue($scenarioLimit, [$limitKey]),
            'operator' => '<=',
        ];
    }
}

$failed = false;
$report = [];

foreach ($checks as $name => $check) {
    $passed = $check['operator'] === '>='
        ? $check['actual'] >= $check['limit']
        : $check['actual'] <= $check['limit'];

    $report[$name] = [
        'actual' => $check['actual'],
        'operator' => $check['operator'],
        'limit' => $check['limit'],
        'passed' => $passed,
    ];

    if (!$passed) {
        $failed = true;
    }
}

$output = [
    'profile' => $profile,
    'passed' => !$failed,
    'reference_hardware' => $hardware,
    'load' => [
        'requests' => numericValue($result, ['requests']),
        'concurrency' => numericValue($result, ['concurrency']),
    ],
    'checks' => $report,
];

echo json_encode(
    $output,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_PRETTY_PRINT
    | JSON_THROW_ON_ERROR,
) . PHP_EOL;

if ($failed) {
    fwrite(
        STDERR,
        "Release-gate не пройден: один или несколько показателей вышли за зафиксированные пороги.\n",
    );
    exit(1);
}

echo "Release-gate по производительности пройден.\n";

/**
 * @return array<string,mixed>
 */
function readJsonFile(string $path, string $label): array
{
    if (!is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "Не удалось прочитать {$label}: {$path}\n");
        exit(2);
    }

    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        fwrite(STDERR, "Не удалось прочитать {$label}: {$path}\n");
        exit(2);
    }

    try {
        $decoded = json_decode(
            $contents,
            true,
            64,
            JSON_THROW_ON_ERROR,
        );
    } catch (JsonException $error) {
        fwrite(
            STDERR,
            "Некорректный JSON в {$label}: {$error->getMessage()}\n",
        );
        exit(2);
    }

    if (!is_array($decoded)) {
        fwrite(STDERR, "{$label} должен быть JSON-объектом.\n");
        exit(2);
    }

    return $decoded;
}

/**
 * @param array<string,mixed> $source
 * @param list<string> $path
 */
function numericValue(array $source, array $path): float|int
{
    $value = $source;

    foreach ($path as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            fwrite(
                STDERR,
                'Не найден обязательный показатель: '
                . implode('.', $path)
                . "\n",
            );
            exit(2);
        }

        $value = $value[$segment];
    }

    if (!is_int($value) && !is_float($value)) {
        fwrite(
            STDERR,
            'Показатель '
            . implode('.', $path)
            . " должен быть числом.\n",
        );
        exit(2);
    }

    return $value;
}
