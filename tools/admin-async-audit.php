#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$templateRoot = $root . '/themes/default/templates/admin';
$layoutPath = $root . '/themes/default/templates/layouts/admin.php';
$scriptPath = $root . '/themes/default/assets/js/admin-async.js';

$errors = [];
$postForms = 0;
$explicitBypasses = [];

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
$files[] = $layoutPath;

foreach ($files as $path) {
    $content = file_get_contents($path);
    if (!is_string($content)) {
        $errors[] = 'Не удалось прочитать ' . str_replace($root . '/', '', $path) . '.';
        continue;
    }

    preg_match_all('/<form\b[^>]*>/i', $content, $forms);
    foreach ($forms[0] as $form) {
        if (!preg_match('/\bmethod\s*=\s*["\']post["\']/i', $form)) {
            continue;
        }

        ++$postForms;
        if (preg_match('/\bdata-no-ajax\b/i', $form)) {
            $explicitBypasses[] = [
                'path' => str_replace($root . '/', '', $path),
                'form' => $form,
            ];
        }
    }
}

if ($postForms === 0) {
    $errors[] = 'Не найдено ни одной POST-формы Admin Shell: аудит не отражает реальные сценарии.';
}

foreach ($explicitBypasses as $bypass) {
    $allowedLogout = $bypass['path'] === 'themes/default/templates/layouts/admin.php'
        && str_contains($bypass['form'], "admin_logout");
    if (!$allowedLogout) {
        $errors[] = 'Необъяснённый отказ от AJAX в ' . $bypass['path'] . ': ' . $bypass['form'];
    }
}

$layout = file_get_contents($layoutPath);
if (!is_string($layout)
    || !str_contains($layout, "asset('js/admin-async.js')")
    || !str_contains($layout, 'data-admin-async-status')) {
    $errors[] = 'Admin Shell не подключает AJAX-слой и/или live status region.';
}

$script = file_get_contents($scriptPath);
if (!is_string($script)) {
    $errors[] = 'Не найден AJAX-слой Admin Shell.';
} else {
    $requiredFragments = [
        'fetch(',
        'new FormData(form)',
        "credentials: 'same-origin'",
        "'X-ChurchCMS-Async': '1'",
        "announce('Выполняется операция…', 'loading')",
        "announce('Операция выполнена.', 'success')",
    ];

    foreach ($requiredFragments as $fragment) {
        if (!str_contains($script, $fragment)) {
            $errors[] = 'AJAX-слой не содержит обязательный контракт: ' . $fragment;
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[ОШИБКА] ' . $error . PHP_EOL);
    }
    exit(1);
}

echo '[OK] AJAX/UX: POST-форм найдено: ' . $postForms
    . '; явных исключений: ' . count($explicitBypasses)
    . ' (разрешён только выход из системы).' . PHP_EOL;
exit(0);
