#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$layout = $root . '/themes/default/templates/layouts/admin.php';
$adminRoot = $root . '/themes/default/templates/admin';
$cssPath = $root . '/themes/default/assets/css/site.css';

$errors = [];

/**
 * @param list<string> $needles
 */
function requireFragments(
    string $path,
    string $content,
    array $needles,
    array &$errors,
): void {
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = basename($path) . ': отсутствует обязательный фрагмент ' . $needle;
        }
    }
}

function relativeLuminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        throw new RuntimeException('Ожидался шестизначный HEX-цвет.');
    }

    $components = [];
    foreach ([0, 2, 4] as $offset) {
        $channel = hexdec(substr($hex, $offset, 2)) / 255;
        $components[] = $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }

    return 0.2126 * $components[0]
        + 0.7152 * $components[1]
        + 0.0722 * $components[2];
}

function contrastRatio(string $foreground, string $background): float
{
    $a = relativeLuminance($foreground);
    $b = relativeLuminance($background);
    $lighter = max($a, $b);
    $darker = min($a, $b);

    return ($lighter + 0.05) / ($darker + 0.05);
}

$layoutContent = file_get_contents($layout);
if (!is_string($layoutContent)) {
    fwrite(STDERR, "Не удалось прочитать admin layout.\n");
    exit(1);
}

requireFragments(
    $layout,
    $layoutContent,
    [
        '<html lang="ru">',
        'class="skip-link"',
        'href="#admin-content"',
        'id="admin-content"',
        'aria-label="Управление сайтом"',
        'aria-label="Разделы"',
        'aria-current="page"',
        'aria-controls="admin-sidebar"',
        'aria-expanded="true"',
        'role="search"',
        'admin-visually-hidden',
    ],
    $errors,
);

$paths = [$layout];
foreach (new DirectoryIterator($adminRoot) as $entry) {
    if (
        $entry->isFile()
        && strtolower($entry->getExtension()) === 'php'
    ) {
        $paths[] = $entry->getPathname();
    }
}

foreach ($paths as $path) {
    $content = file_get_contents($path);
    if (!is_string($content)) {
        $errors[] = basename($path) . ': файл не читается.';
        continue;
    }

    preg_match_all('/<button\b[^>]*>/i', $content, $buttons);
    foreach ($buttons[0] as $button) {
        if (preg_match('/\btype\s*=\s*["\'][^"\']+["\']/i', $button) !== 1) {
            $errors[] = basename($path) . ': кнопка без явного type.';
        }
    }

    preg_match_all('/<a\b[^>]*target\s*=\s*["\']_blank["\'][^>]*>/i', $content, $blankLinks);
    foreach ($blankLinks[0] as $link) {
        if (
            preg_match('/\brel\s*=\s*["\'][^"\']*noopener[^"\']*["\']/i', $link)
            !== 1
        ) {
            $errors[] = basename($path) . ': target=_blank без rel=noopener.';
        }
    }

    if (preg_match('/tabindex\s*=\s*["\']?[1-9][0-9]*/i', $content) === 1) {
        $errors[] = basename($path) . ': найден положительный tabindex.';
    }

    if (preg_match('/\bon(?:click|keydown|keyup|keypress)\s*=/i', $content) === 1) {
        $errors[] = basename($path) . ': найден inline обработчик мыши/клавиатуры.';
    }
}

$statePath = $adminRoot . '/state.php';
$state = file_get_contents($statePath);
if (is_string($state)) {
    requireFragments(
        $statePath,
        $state,
        [
            "role=\"<?= \$theme->e(\$role) ?>\"",
            "aria-live=\"<?= \$theme->e(\$live) ?>\"",
            'aria-busy="true"',
        ],
        $errors,
    );
}

$css = file_get_contents($cssPath);
if (!is_string($css)) {
    $errors[] = 'site.css: файл не читается.';
} else {
    requireFragments(
        $cssPath,
        $css,
        [
            ':focus-visible',
            '.skip-link',
            'prefers-reduced-motion: reduce',
            'scroll-behavior: auto',
        ],
        $errors,
    );
}

foreach ([
    ['#f8fbff', '#071d36', 'текст боковой панели'],
    ['#ffffff', '#0b315d', 'основная кнопка'],
    ['#ffffff', '#6b1f2d', 'акцентная кнопка'],
] as [$foreground, $background, $label]) {
    $ratio = contrastRatio($foreground, $background);

    if ($ratio < 4.5) {
        $errors[] = sprintf(
            'Контраст «%s» ниже 4.5:1: %.2f.',
            $label,
            $ratio,
        );
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "[a11y] {$error}\n");
    }

    exit(1);
}

echo "Admin accessibility audit OK\n";
