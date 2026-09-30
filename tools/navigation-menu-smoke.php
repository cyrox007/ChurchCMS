<?php

declare(strict_types=1);

use ChurchCMS\Core\Router;
use ChurchCMS\Core\ThemeGlobalDataRegistry;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Pages\NavigationMenuService;
use ChurchCMS\Modules\Pages\PageService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход меню',
    'parish',
);

$pages = PageService::fromDatabase();

$publishedPage = $pages->createDraft(
    title: 'О приходе',
    slug: 'about',
    bodyInput: 'О приходе',
    navigationTitle: 'О нас',
    ownerOrganizationPublicId: $root->publicId,
);
$draftPage = $pages->createDraft(
    title: 'Черновая страница',
    slug: 'draft-page',
    bodyInput: 'Черновик',
    ownerOrganizationPublicId: $root->publicId,
);

$pages->publish($publishedPage);

$menu = NavigationMenuService::fromDatabase();
$primary = $menu->ensurePrimary();

if ($primary->menuKey !== 'primary') {
    fwrite(STDERR, "Основное меню создано с неверным ключом.\n");
    exit(1);
}

$pageItem = $menu->createItem(
    itemType: 'page',
    label: 'О нас',
    pagePublicId: $publishedPage,
    routeName: null,
    externalUrl: null,
    sortOrder: 10,
);
$menu->createItem(
    itemType: 'page',
    label: 'Черновик',
    pagePublicId: $draftPage,
    routeName: null,
    externalUrl: null,
    sortOrder: 20,
);
$routeItem = $menu->createItem(
    itemType: 'route',
    label: 'Главная',
    pagePublicId: null,
    routeName: 'home',
    externalUrl: null,
    sortOrder: 0,
);
$externalItem = $menu->createItem(
    itemType: 'external',
    label: 'Епархия',
    pagePublicId: null,
    routeName: null,
    externalUrl: 'https://example.org/diocese',
    sortOrder: 30,
);
$disabled = $menu->createItem(
    itemType: 'route',
    label: 'Галереи',
    pagePublicId: null,
    routeName: 'gallery_index',
    externalUrl: null,
    sortOrder: 5,
    enabled: false,
);

try {
    $menu->createItem(
        itemType: 'external',
        label: 'Небезопасная ссылка',
        pagePublicId: null,
        routeName: null,
        externalUrl: 'http://example.org/',
        sortOrder: 40,
    );
    fwrite(STDERR, "HTTP-ссылка ошибочно разрешена.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $menu->createItem(
        itemType: 'external',
        label: 'Ссылка с credentials',
        pagePublicId: null,
        routeName: null,
        externalUrl: 'https://user:pass@example.org/',
        sortOrder: 40,
    );
    fwrite(STDERR, "URL с credentials ошибочно разрешён.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $menu->createItem(
        itemType: 'route',
        label: 'Неизвестный route',
        pagePublicId: null,
        routeName: 'admin_dashboard',
        externalUrl: null,
        sortOrder: 40,
    );
    fwrite(STDERR, "Неразрешённый системный route принят.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$navigation = $menu->publicNavigation();

$expected = [
    [
        'label' => 'Главная',
        'url' => '/',
    ],
    [
        'label' => 'О нас',
        'url' => '/pages/about',
    ],
    [
        'label' => 'Епархия',
        'url' => 'https://example.org/diocese',
    ],
];

if ($navigation !== $expected) {
    fwrite(
        STDERR,
        "Публичное меню сформировано неверно: "
        . json_encode(
            $navigation,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES,
        )
        . "\n",
    );
    exit(1);
}

$global = ThemeGlobalDataRegistry::data();

if (($global['navigation'] ?? null) !== $expected) {
    fwrite(
        STDERR,
        "Глобальный provider темы не передал navigation.\n",
    );
    exit(1);
}

$header = ThemeRenderer::fromConfig()->capture(
    'partial.header',
    [
        'siteName' => 'Тестовый приход',
        'navigation' => $expected,
    ],
);

foreach ([
    'Основная навигация',
    'О нас',
    '/pages/about',
    'https://example.org/diocese',
] as $needle) {
    if (!str_contains($header, $needle)) {
        fwrite(
            STDERR,
            "Header не содержит пункт меню: {$needle}\n",
        );
        exit(1);
    }
}

if (
    str_contains($header, 'Черновик')
    || str_contains($header, 'Галереи')
) {
    fwrite(
        STDERR,
        "Header показал скрытый или draft-пункт.\n",
    );
    exit(1);
}

$menu->updateItem(
    publicId: $externalItem->publicId,
    itemType: 'external',
    label: 'Митрополия',
    pagePublicId: null,
    routeName: null,
    externalUrl: 'https://example.org/metropolia',
    sortOrder: -10,
);

$updatedNavigation = $menu->publicNavigation();

if (
    ($updatedNavigation[0]['label'] ?? null)
        !== 'Митрополия'
    || ($updatedNavigation[0]['url'] ?? null)
        !== 'https://example.org/metropolia'
) {
    fwrite(STDERR, "Обновление порядка меню не применилось.\n");
    exit(1);
}

$menu->deleteItem($routeItem->publicId);

if (
    array_filter(
        $menu->publicNavigation(),
        static fn(array $item): bool =>
            $item['label'] === 'Главная',
    ) !== []
) {
    fwrite(STDERR, "Удалённый пункт остался в меню.\n");
    exit(1);
}

$router = Router::getInstance();

$routes = [
    'admin_navigation' => '/admin/navigation',
    'admin_navigation_item_create' =>
        '/admin/navigation/items',
    'admin_navigation_item_update' =>
        '/admin/navigation/items/'
        . rawurlencode($pageItem->publicId),
    'admin_navigation_item_delete' =>
        '/admin/navigation/items/'
        . rawurlencode($disabled->publicId)
        . '/delete',
];

foreach ($routes as $name => $expectedUrl) {
    $params = match ($name) {
        'admin_navigation_item_update' => [
            'publicId' => $pageItem->publicId,
        ],
        'admin_navigation_item_delete' => [
            'publicId' => $disabled->publicId,
        ],
        default => [],
    };

    if ($router->url($name, $params) !== $expectedUrl) {
        fwrite(
            STDERR,
            "Маршрут меню {$name} зарегистрирован неверно.\n",
        );
        exit(1);
    }
}

echo "Navigation menu smoke OK\n";
