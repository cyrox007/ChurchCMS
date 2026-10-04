#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$record = static function (
    string $name,
    bool $success,
    string $message,
) use (&$failures): void {
    $prefix = $success ? '[OK]' : '[ОШИБКА]';
    echo $prefix . ' ' . $name . ': ' . $message . PHP_EOL;

    if (!$success) {
        $failures[] = $name;
    }
};

$run = static function (
    array $command,
    string $cwd,
): array {
    $escaped = array_map(
        static fn(string $part): string => escapeshellarg($part),
        $command,
    );
    $descriptor = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(
        implode(' ', $escaped),
        $descriptor,
        $pipes,
        $cwd,
    );

    if (!is_resource($process)) {
        return [
            'exit_code' => 1,
            'output' => 'Не удалось запустить проверку.',
        ];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exit_code' => $exitCode,
        'output' => trim(
            (is_string($stdout) ? $stdout : '')
            . PHP_EOL
            . (is_string($stderr) ? $stderr : '')
        ),
    ];
};

$phpVersionOk = version_compare(PHP_VERSION, '8.3.0', '>=');
$record(
    'Версия PHP',
    $phpVersionOk,
    $phpVersionOk
        ? PHP_VERSION
        : 'Требуется PHP 8.3+, обнаружен ' . PHP_VERSION,
);

$requiredExtensions = [
    'json',
    'openssl',
    'pdo',
];
$missingExtensions = array_values(array_filter(
    $requiredExtensions,
    static fn(string $extension): bool => !extension_loaded($extension),
));
$record(
    'Обязательные расширения PHP',
    $missingExtensions === [],
    $missingExtensions === []
        ? implode(', ', $requiredExtensions)
        : 'Отсутствуют: ' . implode(', ', $missingExtensions),
);

$phpFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $root,
        FilesystemIterator::SKIP_DOTS,
    ),
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $phpFiles[] = $path;
}
sort($phpFiles, SORT_STRING);

$syntaxErrors = [];
foreach ($phpFiles as $path) {
    $result = $run(
        [PHP_BINARY, '-l', $path],
        $root,
    );
    if ($result['exit_code'] !== 0) {
        $syntaxErrors[] = str_replace(
            $root . DIRECTORY_SEPARATOR,
            '',
            $path,
        );
    }
}
$record(
    'Синтаксис PHP',
    $syntaxErrors === [],
    $syntaxErrors === []
        ? sprintf('Проверено файлов: %d', count($phpFiles))
        : 'Ошибки: ' . implode(', ', $syntaxErrors),
);

$migrations = $run(
    [PHP_BINARY, 'bin/migrate.php', 'validate'],
    $root,
);
$record(
    'Миграции',
    $migrations['exit_code'] === 0,
    $migrations['exit_code'] === 0
        ? 'Структура миграций валидна.'
        : ($migrations['output'] !== ''
            ? $migrations['output']
            : 'Валидация миграций завершилась ошибкой.'),
);

$themes = $run(
    [PHP_BINARY, 'bin/theme-check.php'],
    $root,
);
$record(
    'Темы',
    $themes['exit_code'] === 0,
    $themes['exit_code'] === 0
        ? 'Все зарегистрированные шаблоны доступны и безопасны.'
        : ($themes['output'] !== ''
            ? $themes['output']
            : 'Проверка тем завершилась ошибкой.'),
);

$performanceResult = null;
$performanceThresholds = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--performance-result=')) {
        $performanceResult = substr(
            $argument,
            strlen('--performance-result='),
        );
        continue;
    }

    if (str_starts_with($argument, '--performance-thresholds=')) {
        $performanceThresholds = substr(
            $argument,
            strlen('--performance-thresholds='),
        );
    }
}

if ($performanceResult !== null || $performanceThresholds !== null) {
    if ($performanceResult === null || $performanceThresholds === null) {
        $record(
            'Performance release-gate',
            false,
            'Параметры --performance-result и --performance-thresholds должны передаваться вместе.',
        );
    } else {
        $performance = $run(
            [
                PHP_BINARY,
                'tools/performance/release-gate.php',
                '--result=' . $performanceResult,
                '--thresholds=' . $performanceThresholds,
            ],
            $root,
        );
        $record(
            'Performance release-gate',
            $performance['exit_code'] === 0,
            $performance['output'] !== ''
                ? $performance['output']
                : 'Проверка производительности завершена.',
        );
    }
} else {
    echo '[ИНФО] Performance release-gate: пропущен. '
        . 'Перед релизом 1.0 запустите аудит на эталонном стенде '
        . 'с реальными result/thresholds файлами.'
        . PHP_EOL;
}

echo PHP_EOL;
if ($failures !== []) {
    fwrite(
        STDERR,
        'Предрелизный аудит НЕ ПРОЙДЕН. Ошибок: '
        . count($failures)
        . '. Проверки: '
        . implode(', ', $failures)
        . PHP_EOL,
    );
    exit(1);
}

echo 'Предрелизный аудит пройден. '
    . 'Для финального 1.0 дополнительно обязателен performance release-gate '
    . 'на зафиксированном эталонном стенде.'
    . PHP_EOL;
exit(0);
