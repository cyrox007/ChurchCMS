#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', [
    'output::',
    'web-server::',
    'php-runtime::',
    'database::',
    'storage::',
]);

$root = dirname(__DIR__, 2);
$output = trim((string) ($options['output'] ?? ''));

try {
    $hardware = [
        'cpu' => detectCpu(),
        'memory_mb' => detectMemoryMb(),
        'storage' => optionOrDetected(
            $options,
            'storage',
            detectStorage(),
        ),
        'web_server' => optionOrDetected(
            $options,
            'web-server',
            detectWebServer(),
        ),
        'php' => optionOrDetected(
            $options,
            'php-runtime',
            detectPhpRuntime(),
        ),
        'database' => optionOrDetected(
            $options,
            'database',
            detectDatabase($root),
        ),
    ];

    validateHardware($hardware);

    $profile = [
        'schema' => 1,
        'captured_at' => gmdate('c'),
        'reference_hardware' => $hardware,
        'environment' => [
            'os' => detectOperatingSystem(),
            'kernel' => php_uname('r'),
            'architecture' => php_uname('m'),
        ],
    ];

    $json = json_encode(
        $profile,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
        | JSON_THROW_ON_ERROR,
    ) . PHP_EOL;

    if ($output === '') {
        echo $json;
        exit(0);
    }

    writeAtomically($output, $json);
    echo "Профиль эталонного сервера сохранён: {$output}\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(
        STDERR,
        'Не удалось собрать профиль эталонного сервера: '
        . $error->getMessage()
        . PHP_EOL,
    );
    exit(1);
}

/**
 * @param array<string,mixed> $options
 */
function optionOrDetected(
    array $options,
    string $name,
    string $detected,
): string {
    $override = trim((string) ($options[$name] ?? ''));

    return $override !== '' ? $override : $detected;
}

function detectCpu(): string
{
    $model = '';
    $logicalCores = 0;
    $cpuInfo = @file('/proc/cpuinfo', FILE_IGNORE_NEW_LINES);

    if (is_array($cpuInfo)) {
        foreach ($cpuInfo as $line) {
            if (str_starts_with($line, 'processor')) {
                $logicalCores++;
                continue;
            }

            if ($model !== '' || !str_starts_with($line, 'model name')) {
                continue;
            }

            $parts = explode(':', $line, 2);
            $model = trim($parts[1] ?? '');
        }
    }

    if ($logicalCores < 1) {
        $logicalCores = 1;
    }

    if ($model === '') {
        $model = trim(php_uname('m'));
    }

    return sprintf(
        '%s; логических ядер: %d',
        $model,
        $logicalCores,
    );
}

function detectMemoryMb(): int
{
    $contents = @file_get_contents('/proc/meminfo');
    if (is_string($contents)
        && preg_match('/^MemTotal:\s+(\d+)\s+kB$/m', $contents, $matches) === 1
    ) {
        return max(1, (int) floor(((int) $matches[1]) / 1024));
    }

    throw new RuntimeException(
        'Не удалось определить общий объём оперативной памяти. '
        . 'Запустите инструмент на Linux-стенде.',
    );
}

function detectStorage(): string
{
    $total = @disk_total_space('/');
    $free = @disk_free_space('/');

    if (!is_float($total) || !is_float($free) || $total <= 0) {
        throw new RuntimeException(
            'Не удалось определить параметры корневого файлового хранилища.',
        );
    }

    return sprintf(
        'корневой раздел: %.1f GiB всего, %.1f GiB свободно',
        $total / 1073741824,
        $free / 1073741824,
    );
}

function detectWebServer(): string
{
    foreach ([
        ['nginx', '-v'],
        ['apache2', '-v'],
        ['httpd', '-v'],
    ] as $command) {
        $result = runCommand($command);
        if ($result['exit_code'] !== 0 || $result['output'] === '') {
            continue;
        }

        return firstLine($result['output']);
    }

    throw new RuntimeException(
        'Не удалось автоматически определить веб-сервер. '
        . 'Передайте --web-server="Nginx ...".',
    );
}

function detectPhpRuntime(): string
{
    foreach ([
        ['php-fpm8.3', '-v'],
        ['php-fpm', '-v'],
    ] as $command) {
        $result = runCommand($command);
        if ($result['exit_code'] !== 0 || $result['output'] === '') {
            continue;
        }

        return firstLine($result['output']);
    }

    return sprintf(
        'PHP %s; FPM не определён автоматически',
        PHP_VERSION,
    );
}

function detectDatabase(string $root): string
{
    $localConfig = $root . '/config/local.php';
    if (is_file($localConfig)) {
        try {
            require_once $root . '/core.php';
            $pdo = \ChurchCMS\Core\DatabaseManager::getInstance()->connection();
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'pgsql') {
                $version = $pdo->query('SHOW server_version')?->fetchColumn();
                if (is_string($version) && trim($version) !== '') {
                    return 'PostgreSQL ' . trim($version);
                }
            }

            if ($driver === 'mysql') {
                $version = $pdo->query('SELECT VERSION()')?->fetchColumn();
                if (is_string($version) && trim($version) !== '') {
                    return 'MySQL ' . trim($version);
                }
            }
        } catch (Throwable) {
            // Ниже остаётся безопасный fallback по версии локального клиента.
        }
    }

    foreach ([
        ['psql', '--version'],
        ['mysql', '--version'],
    ] as $command) {
        $result = runCommand($command);
        if ($result['exit_code'] === 0 && $result['output'] !== '') {
            return firstLine($result['output']);
        }
    }

    throw new RuntimeException(
        'Не удалось определить СУБД. '
        . 'Передайте --database="PostgreSQL ..." или --database="MySQL ...".',
    );
}

function detectOperatingSystem(): string
{
    $release = @file('/etc/os-release', FILE_IGNORE_NEW_LINES);
    if (is_array($release)) {
        foreach ($release as $line) {
            if (!str_starts_with($line, 'PRETTY_NAME=')) {
                continue;
            }

            return trim(
                substr($line, strlen('PRETTY_NAME=')),
                " \t\n\r\0\x0B\"'",
            );
        }
    }

    return PHP_OS_FAMILY;
}

/**
 * @param array<int,string> $command
 * @return array{exit_code:int,output:string}
 */
function runCommand(array $command): array
{
    $descriptor = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open(
        $command,
        $descriptor,
        $pipes,
        null,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        return [
            'exit_code' => 127,
            'output' => '',
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
            . (is_string($stderr) ? $stderr : ''),
        ),
    ];
}

function firstLine(string $value): string
{
    $lines = preg_split('/\R/', trim($value));

    return trim((string) ($lines[0] ?? ''));
}

/**
 * @param array<string,mixed> $hardware
 */
function validateHardware(array $hardware): void
{
    foreach (['cpu', 'storage', 'web_server', 'php', 'database'] as $field) {
        $value = $hardware[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                "Не заполнено обязательное поле профиля: {$field}.",
            );
        }
    }

    $memory = $hardware['memory_mb'] ?? null;
    if (!is_int($memory) || $memory < 1) {
        throw new RuntimeException(
            'Не удалось получить корректный объём оперативной памяти.',
        );
    }
}

function writeAtomically(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        throw new RuntimeException(
            "Каталог результата не существует: {$directory}",
        );
    }

    $temporary = tempnam($directory, '.churchcms-reference-');
    if (!is_string($temporary)) {
        throw new RuntimeException(
            'Не удалось создать временный файл профиля.',
        );
    }

    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException(
                'Не удалось записать временный файл профиля.',
            );
        }

        if (!rename($temporary, $path)) {
            throw new RuntimeException(
                "Не удалось сохранить профиль: {$path}",
            );
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
}
