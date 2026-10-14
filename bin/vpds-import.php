<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/modules/migration/VpdsImportPort.php';
require_once $root . '/modules/migration/VpdsImportReport.php';
require_once $root . '/modules/migration/VpdsManifestReader.php';
require_once $root . '/modules/migration/VpdsImportService.php';
require_once $root . '/modules/migration/DatabaseVpdsImportPort.php';
require_once $root . '/core.php';

use ChurchCMS\Modules\Migration\DatabaseVpdsImportPort;
use ChurchCMS\Modules\Migration\VpdsImportService;
use ChurchCMS\Modules\Migration\VpdsManifestReader;

function vpdsUsage(): string
{
    return <<<'TEXT'
Импорт наследия VPDS в ChurchCMS.

Использование:
  php bin/vpds-import.php \
    --organization=123 \
    --manifest=/srv/vpds/export/manifest.json \
    --media-root=/srv/vpds/export/media \
    [--dry-run] \
    [--report=/srv/vpds/import-report.json]

Перед реальным импортом обязательно выполните прогон с --dry-run и проверьте отчёт.
TEXT;
}

/** @param array<string,mixed> $report */
function vpdsWriteReport(string $path, array $report): void
{
    $directory = dirname($path);
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('Каталог отчёта VPDS недоступен для записи.');
    }

    $json = json_encode(
        $report,
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ) . PHP_EOL;
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));

    if (file_put_contents($temporary, $json, LOCK_EX) === false) {
        throw new RuntimeException('Не удалось записать временный отчёт VPDS.');
    }

    @chmod($temporary, 0640);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Не удалось атомарно сохранить отчёт VPDS.');
    }
}

$options = getopt('', [
    'organization:',
    'manifest:',
    'media-root:',
    'dry-run',
    'report:',
    'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, vpdsUsage() . PHP_EOL);
    exit(0);
}

try {
    $organizationId = (int) ($options['organization'] ?? 0);
    $manifestPath = trim((string) ($options['manifest'] ?? ''));
    $mediaRoot = trim((string) ($options['media-root'] ?? ''));
    $dryRun = array_key_exists('dry-run', $options);
    $reportPath = trim((string) ($options['report'] ?? ''));

    if ($organizationId <= 0 || $manifestPath === '' || $mediaRoot === '') {
        throw new InvalidArgumentException(
            "Не заданы обязательные параметры.\n\n" . vpdsUsage()
        );
    }

    if (!is_dir($mediaRoot) || !is_readable($mediaRoot)) {
        throw new InvalidArgumentException(
            'Корневой каталог Media VPDS недоступен для чтения.'
        );
    }

    $manifest = (new VpdsManifestReader())->read($manifestPath);
    $report = (new VpdsImportService(DatabaseVpdsImportPort::fromRuntime()))
        ->import(
            organizationId: $organizationId,
            manifest: $manifest,
            mediaRoot: $mediaRoot,
            dryRun: $dryRun,
        );
    $payload = $report->toArray();
    $json = $report->toJson() . PHP_EOL;

    fwrite(STDOUT, $json);

    if ($reportPath !== '') {
        vpdsWriteReport($reportPath, $payload);
        fwrite(STDERR, 'Отчёт VPDS сохранён: ' . $reportPath . PHP_EOL);
    }

    if ($dryRun) {
        fwrite(STDERR, 'VPDS: выполнен пробный прогон, данные ChurchCMS не изменялись.' . PHP_EOL);
    } else {
        fwrite(STDERR, 'VPDS: импорт завершён, сверьте отчёт и публичные страницы.' . PHP_EOL);
    }

    exit(($payload['success'] ?? false) === true ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, 'Ошибка импорта VPDS: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}
