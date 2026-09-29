<?php

declare(strict_types=1);

use ChurchCMS\Core\ThemeRenderer;

require dirname(__DIR__) . '/core.php';

$html = ThemeRenderer::forTheme('default')->capture(
    'page.home',
    [
        'heading' => 'Тестовая епархия',
        'lead' => 'Проверка общей ленты.',
        'latestPublications' => [
            [
                'id' => '10000000-0000-4000-8000-000000000001',
                'title' => 'Локальная новость',
                'excerpt' => 'Материал текущего сайта.',
                'url' => 'https://diocese.example/publications/local',
                'source' => [
                    'kind' => 'local',
                    'name' => 'Тестовая епархия',
                ],
            ],
            [
                'id' => '20000000-0000-4000-8000-000000000002',
                'title' => 'Новость <script>alert(1)</script>',
                'excerpt' => 'Материал дочернего прихода.',
                'url' => 'https://child.example/publications/remote',
                'source' => [
                    'kind' => 'federation',
                    'name' => 'Дочерний приход',
                ],
            ],
        ],
    ],
);

foreach ([
    'Последние публикации',
    'Локальная новость',
    'Этот сайт',
    'Источник · Дочерний приход',
    'https://child.example/publications/remote',
    'Открыть на исходном сайте',
    'Новость &lt;script&gt;alert(1)&lt;/script&gt;',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(
            STDERR,
            "Публичный блок общей ленты не содержит: {$expected}\n",
        );
        exit(1);
    }
}

if (str_contains($html, '<script>alert(1)</script>')) {
    fwrite(
        STDERR,
        "Публичный блок вывел неэкранированный remote HTML.\n",
    );
    exit(1);
}

$empty = ThemeRenderer::forTheme('default')->capture(
    'page.home',
    [
        'heading' => 'Тестовая епархия',
        'latestPublications' => [],
    ],
);

if (str_contains($empty, 'Последние публикации')) {
    fwrite(
        STDERR,
        "Пустой блок публикаций не должен занимать место на главной.\n",
    );
    exit(1);
}

echo "Публичный блок агрегированных публикаций проверен\n";
