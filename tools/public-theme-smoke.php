<?php

declare(strict_types=1);

use ChurchCMS\Core\ThemeRenderer;

require dirname(__DIR__) . '/core.php';

$theme = ThemeRenderer::fromConfig();
$navigation = [
    ['label' => 'Главная', 'url' => '/'],
    ['label' => 'О храме', 'url' => '/pages/about'],
    ['label' => 'Богослужения', 'url' => '/worship'],
];

$header = $theme->capture('partial.header', [
    'siteName' => 'Никольский храм',
    'navigation' => $navigation,
]);

foreach ([
    'Основная навигация',
    'Никольский храм',
    'Новости и публикации',
    'Администратору',
] as $expected) {
    if (!str_contains($header, $expected)) {
        fwrite(
            STDERR,
            "Публичная шапка не содержит: {$expected}\n",
        );
        exit(1);
    }
}

$home = $theme->capture('page.home', [
    'heading' => 'Никольский храм',
    'lead' => 'Официальный сайт прихода.',
    'navigation' => $navigation,
    'latestPublications' => [
        [
            'type' => 'news',
            'title' => 'Приходская новость',
            'excerpt' => 'Краткое описание новости.',
            'published_at' => '2026-10-14T08:00:00+03:00',
            'url' => '/publications/news',
            'source' => [
                'kind' => 'local',
                'name' => 'Никольский храм',
            ],
        ],
        [
            'type' => 'announcement',
            'title' => 'Объявление прихода',
            'excerpt' => 'Важная информация.',
            'published_at' => '2026-10-13T08:00:00+03:00',
            'url' => '/publications/announcement',
            'source' => [
                'kind' => 'local',
                'name' => 'Никольский храм',
            ],
        ],
    ],
]);

foreach ([
    'Последние новости и материалы',
    'Приходская новость',
    'Объявление прихода',
    'Полезные разделы сайта',
] as $expected) {
    if (!str_contains($home, $expected)) {
        fwrite(
            STDERR,
            "Главная страница не содержит: {$expected}\n",
        );
        exit(1);
    }
}

foreach ([
    'Проверить runtime',
    'PHP 8.3+',
    '0 deps',
] as $legacyDemoText) {
    if (str_contains($home, $legacyDemoText)) {
        fwrite(
            STDERR,
            "На главной остался демонстрационный текст: {$legacyDemoText}\n",
        );
        exit(1);
    }
}

$layout = $theme->capture('layout.main', [
    'title' => 'Никольский храм',
    'siteName' => 'Никольский храм',
    'navigation' => $navigation,
    'content' => $home,
]);

foreach ([
    'public-theme',
    'public-frame',
    'section-nav',
    'public-modern.css',
    'public-content.css',
] as $expected) {
    if (!str_contains($layout, $expected)) {
        fwrite(
            STDERR,
            "Публичная компоновка не содержит: {$expected}\n",
        );
        exit(1);
    }
}

$events = $theme->capture('events.index', [
    'events' => [['title' => 'Событие']],
    'calendar' => [
        'month' => 'октябрь 2026',
        'days' => [
            '14.10.2026' => [
                [
                    'title' => 'Приходская встреча',
                    'url' => '/events/1',
                    'location_name' => 'Приходской дом',
                ],
            ],
        ],
    ],
]);

if (!str_contains($events, 'event-day')) {
    fwrite(STDERR, "Календарь событий не использует новый шаблон.\n");
    exit(1);
}

$worship = $theme->capture('worship.index', [
    'services' => [
        [
            'starts_at' => '2026-10-14T09:00:00+03:00',
            'title' => 'Божественная литургия',
            'url' => '/worship/1',
            'location_name' => 'Главный храм',
            'status' => 'scheduled',
        ],
    ],
]);

if (!str_contains($worship, 'service-card')) {
    fwrite(STDERR, "Расписание богослужений не использует новый шаблон.\n");
    exit(1);
}

echo "Публичная тема: проверка пройдена.\n";
