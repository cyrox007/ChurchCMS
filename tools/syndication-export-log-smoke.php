<?php

declare(strict_types=1);

require dirname(__DIR__) . '/core.php';

use ChurchCMS\Core\SyndicationExportLogRepository;

$repository = SyndicationExportLogRepository::fromDefaultConnection();
$repository->record('rss', 'success', 3, 1024, 12);
$repository->record('rambler', 'failed', 2, 0, 7, 'generation_failed');

$recent = $repository->recent(10);
if (count($recent) !== 2) {
    throw new RuntimeException('Журнал должен вернуть две тестовые записи.');
}

$rss = $repository->recent(10, 'rss');
if (count($rss) !== 1) {
    throw new RuntimeException('Фильтр по RSS должен вернуть одну запись.');
}

$rssEntry = $rss[0];
if (
    $rssEntry['status'] !== 'success'
    || (int) $rssEntry['entry_count'] !== 3
    || (int) $rssEntry['body_bytes'] !== 1024
    || (int) $rssEntry['duration_ms'] !== 12
    || $rssEntry['error_code'] !== null
) {
    throw new RuntimeException('Успешный экспорт сохранён с неверными метаданными.');
}

$rambler = $repository->recent(10, 'rambler');
if (
    count($rambler) !== 1
    || $rambler[0]['status'] !== 'failed'
    || $rambler[0]['error_code'] !== 'generation_failed'
) {
    throw new RuntimeException('Ошибка экспорта сохранена неверно.');
}

$controller = file_get_contents(
    dirname(__DIR__) . '/app/controllers/SyndicationController.php',
);
if (!is_string($controller)) {
    throw new RuntimeException('Не удалось прочитать SyndicationController.');
}
if (!str_contains($controller, 'if ($body === null)')) {
    throw new RuntimeException('Журналирование должно происходить только при формировании фида.');
}
if (substr_count($controller, '$this->recordExport(') < 2) {
    throw new RuntimeException('Контроллер должен журналировать успех и ошибку формирования.');
}
if (!str_contains($controller, "'generation_failed'")) {
    throw new RuntimeException('Ошибка формирования должна иметь безопасный код.');
}

fwrite(STDOUT, "Журнал экспортов синдикации работает корректно.\n");
