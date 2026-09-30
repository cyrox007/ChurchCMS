<?php

declare(strict_types=1);

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Router;
use ChurchCMS\Core\ThemeContext;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Redirects\RedirectRepository;
use ChurchCMS\Modules\Redirects\RedirectRequestInterceptor;
use ChurchCMS\Modules\Redirects\RedirectService;
use InvalidArgumentException;

require dirname(__DIR__) . '/core.php';

$service = RedirectService::fromDatabase();
$repository = RedirectRepository::fromDatabase();

$rule = $service->create(
    sourcePath: '/старое/объявление',
    targetPath: '/pages/new-announcement?from=legacy',
    statusCode: 301,
);

$encodedSource =
    '/%D1%81%D1%82%D0%B0%D1%80%D0%BE%D0%B5/'
    . '%D0%BE%D0%B1%D1%8A%D1%8F%D0%B2%D0%BB%D0%B5%D0%BD%D0%B8%D0%B5';

if (
    $rule->sourcePath !== $encodedSource
    || $rule->targetPath
        !== '/pages/new-announcement?from=legacy'
    || !$rule->enabled
    || $rule->statusCode !== 301
) {
    fwrite(
        STDERR,
        "Redirect rule канонизирован неверно.\n",
    );
    exit(1);
}

$matched = $service->match($encodedSource);

if (
    $matched === null
    || $matched->publicId !== $rule->publicId
) {
    fwrite(
        STDERR,
        "Redirect rule не найден по request path.\n",
    );
    exit(1);
}

$service->recordHit($matched);
$afterHit = $repository->findByPublicId(
    $rule->publicId,
);

if (
    $afterHit === null
    || $afterHit->hitCount !== 1
    || $afterHit->lastHitAt === null
) {
    fwrite(
        STDERR,
        "Счётчик redirect hit не обновился.\n",
    );
    exit(1);
}

$expectInvalid = static function (
    callable $action,
    string $message,
): void {
    try {
        $action();
        fwrite(STDERR, $message . "\n");
        exit(1);
    } catch (InvalidArgumentException) {
    }
};

$expectInvalid(
    static fn() => $service->create(
        '/external',
        'https://example.org/',
    ),
    'Внешняя цель редиректа ошибочно разрешена.',
);

$expectInvalid(
    static fn() => $service->create(
        '/admin/old',
        '/pages/new',
    ),
    'Системный /admin ошибочно разрешён как source.',
);

$expectInvalid(
    static fn() => $service->create(
        '/api/old',
        '/pages/new',
    ),
    'Системный /api ошибочно разрешён как source.',
);

$expectInvalid(
    static fn() => $service->create(
        '/same',
        '/same',
    ),
    'Self-loop редиректа ошибочно разрешён.',
);

$expectInvalid(
    static fn() => $service->create(
        '/bad-status',
        '/pages/new',
        305,
    ),
    'Неподдерживаемый HTTP status ошибочно разрешён.',
);

$expectInvalid(
    static fn() => $service->create(
        '/fragment',
        '/pages/new#section',
    ),
    'Fragment в target ошибочно разрешён.',
);

$first = $service->create(
    '/legacy-a',
    '/legacy-b',
);

$expectInvalid(
    static fn() => $service->create(
        '/legacy-b',
        '/pages/final',
    ),
    'Redirect chain A→B→C ошибочно разрешена.',
);

$expectInvalid(
    static fn() => $service->create(
        '/legacy-c',
        '/legacy-a',
    ),
    'Обратная redirect chain ошибочно разрешена.',
);

$service->update(
    publicId: $first->publicId,
    sourcePath: '/legacy-a',
    targetPath: '/legacy-b',
    statusCode: 302,
    enabled: false,
);

$second = $service->create(
    '/legacy-b',
    '/pages/final',
    308,
);

if (
    $second->statusCode !== 308
    || !$second->enabled
) {
    fwrite(
        STDERR,
        "Redirect после отключения конфликтного правила не создан.\n",
    );
    exit(1);
}

if ($service->match('/admin/redirects') !== null) {
    fwrite(
        STDERR,
        "Системный путь ошибочно совпал с redirect rule.\n",
    );
    exit(1);
}

$interceptor = new RedirectRequestInterceptor();
$interceptor->handle(
    new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/admin/redirects',
        ],
    ),
    '/admin/redirects',
);

$interceptor->handle(
    new Request(
        server: [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => $encodedSource,
        ],
    ),
    $encodedSource,
);

$router = Router::getInstance();

foreach ([
    'admin_redirects' => '/admin/redirects',
    'admin_redirect_create' => '/admin/redirects',
    'admin_redirect_update' =>
        '/admin/redirects/' . rawurlencode($rule->publicId),
    'admin_redirect_delete' =>
        '/admin/redirects/'
        . rawurlencode($rule->publicId)
        . '/delete',
] as $routeName => $expected) {
    $params = in_array(
        $routeName,
        ['admin_redirects', 'admin_redirect_create'],
        true,
    )
        ? []
        : ['publicId' => $rule->publicId];

    if ($router->url($routeName, $params) !== $expected) {
        fwrite(
            STDERR,
            "Маршрут {$routeName} зарегистрирован неверно.\n",
        );
        exit(1);
    }
}

$renderer = ThemeRenderer::fromConfig();
$theme = new ThemeContext(
    $renderer,
    'default',
);
$redirectRules = $repository->all();
$redirectStatus = null;

ob_start();
require dirname(__DIR__)
    . '/themes/default/templates/admin/redirects.php';
$html = (string) ob_get_clean();

foreach ([
    'Редиректы',
    'name="source_path"',
    'name="target_path"',
    'name="status_code"',
    'name="enabled"',
    'name="csrf_token"',
    'срабатываний: 1',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(
            STDERR,
            "Redirect Admin template не содержит: {$expected}\n",
        );
        exit(1);
    }
}

if (
    !str_contains(
        $html,
        '/%D1%81%D1%82%D0%B0%D1%80%D0%BE%D0%B5/'
    )
    || str_contains(
        $html,
        '<script>'
    )
) {
    fwrite(
        STDERR,
        "Redirect Admin template выводит пути некорректно.\n",
    );
    exit(1);
}

$service->delete($second->publicId);

if (
    $repository->findByPublicId(
        $second->publicId,
    ) !== null
) {
    fwrite(
        STDERR,
        "Удалённый redirect rule остался в БД.\n",
    );
    exit(1);
}

echo "Redirect manager smoke OK\n";
