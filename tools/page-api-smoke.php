<?php

declare(strict_types=1);

use ChurchCMS\Core\RouteTemplate;
use ChurchCMS\Core\Router;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Pages\PageApiResource;
use ChurchCMS\Modules\Pages\PageRepository;
use ChurchCMS\Modules\Pages\PageService;
use ChurchCMS\Modules\Pages\PageStatus;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$organizationRoot = $organizations->ensureSiteRoot(
    'API-приход страниц',
    'parish',
);
$departmentId = $organizations->create(
    name: 'Редакция сайта',
    type: 'department',
    parentPublicId: $organizationRoot->publicId,
);

$service = PageService::fromDatabase();
$repository = PageRepository::fromDatabase();
$rootId = $service->createDraft(
    title: 'API-раздел',
    slug: 'api-pages-smoke',
    bodyInput: 'Корень API-раздела',
    ownerOrganizationPublicId: $departmentId,
);
$childId = $service->createDraft(
    title: 'Дочерняя API-страница',
    slug: 'child',
    bodyInput: 'Дочерняя страница',
    parentPublicId: $rootId,
    ownerOrganizationPublicId: $departmentId,
);
$draftTargetId = $service->createDraft(
    title: 'Черновой раздел',
    slug: 'api-pages-draft-target',
    ownerOrganizationPublicId: $departmentId,
);
$root = $repository->findByPublicId($rootId);
$child = $repository->findByPublicId($childId);

if ($root === null || $child === null) {
    fwrite(STDERR, "Не удалось подготовить страницы API.\n");
    exit(1);
}

$draftProjection = (new PageApiResource(
    $child,
    $root->publicId,
))->toApiArray();
if (
    ($draftProjection['id'] ?? null) !== $childId
    || ($draftProjection['parent_id'] ?? null) !== $rootId
    || ($draftProjection['organization_owner_id'] ?? null)
        !== $departmentId
    || ($draftProjection['path'] ?? null)
        !== '/api-pages-smoke/child'
) {
    fwrite(STDERR, "Page API resource потерял canonical IDs или path.\n");
    exit(1);
}

try {
    $service->publish($childId);
    fwrite(
        STDERR,
        "Дочерняя страница опубликована раньше родительской.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$service->publish($rootId);
$service->publish($childId);

try {
    $service->moveSubtree(
        $rootId,
        $draftTargetId,
    );
    fwrite(
        STDERR,
        "Опубликованный раздел перемещён под черновик.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$published = $repository->published();
$publishedIds = array_map(
    static fn($page): string => $page->publicId,
    $published,
);

if (
    !in_array($rootId, $publishedIds, true)
    || !in_array($childId, $publishedIds, true)
    || $repository->findPublishedByPublicId($childId) === null
    || $repository->findPublishedByPath(
        '/api-pages-smoke/child',
    )?->publicId !== $childId
    || $repository->countPublished() !== 2
) {
    fwrite(STDERR, "Published Page API repository вернул неверное дерево.\n");
    exit(1);
}

$parentIds = $repository->publicIdsByIds([
    $child->parentId ?? 0,
]);
if (($parentIds[$child->parentId ?? 0] ?? null) !== $rootId) {
    fwrite(STDERR, "API не разрешил parent DB ID в stable public ID.\n");
    exit(1);
}

$router = Router::getInstance();
if (
    $router->url('api_v1_pages') !== '/api/v1/pages'
    || $router->url(
        'api_v1_page_show',
        ['publicId' => $childId],
    ) !== '/api/v1/pages/' . $childId
    || $router->url(
        'page_show',
        ['path' => '/api-pages-smoke/child'],
    ) !== '/pages/api-pages-smoke/child'
) {
    fwrite(STDERR, "Маршруты Page API не зарегистрированы.\n");
    exit(1);
}

try {
    $router->url(
        'page_show',
        ['path' => '../secret'],
    );
    fwrite(STDERR, "Генератор URL разрешил traversal-сегмент.\n");
    exit(1);
} catch (RuntimeException) {
}

$wildcard = RouteTemplate::compile('/pages/{path*}');
if (
    preg_match(
        $wildcard,
        '/pages/api-pages-smoke/child',
        $matches,
    ) !== 1
    || ($matches['path'] ?? null) !== 'api-pages-smoke/child'
    || preg_match($wildcard, '/pages') === 1
) {
    fwrite(STDERR, "Wildcard route небезопасно разобрал canonical path.\n");
    exit(1);
}

try {
    RouteTemplate::compile('/pages/{path*}/extra');
    fwrite(STDERR, "Wildcard route разрешён не в конце шаблона.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    RouteTemplate::compile('/{section*}/{path*}');
    fwrite(STDERR, "Маршрут разрешил несколько wildcard-параметров.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$rendered = ThemeRenderer::fromConfig()->capture(
    'page.show',
    [
        'page' => $child,
        'canonicalPath' => '/pages/api-pages-smoke/child',
        'breadcrumbs' => [
            [
                'title' => $root->title,
                'path' => '/pages/api-pages-smoke',
                'current' => false,
            ],
            [
                'title' => $child->title,
                'path' => '/pages/api-pages-smoke/child',
                'current' => true,
            ],
        ],
    ],
);

foreach ([
    'Дочерняя API-страница',
    'Дочерняя страница',
    '/pages/api-pages-smoke',
    'aria-current="page"',
] as $expected) {
    if (!str_contains($rendered, $expected)) {
        fwrite(STDERR, "Public Page template не содержит: {$expected}\n");
        exit(1);
    }
}

$service->unpublish($rootId);
$rootAfter = $repository->findByPublicId($rootId);
$childAfter = $repository->findByPublicId($childId);

if (
    $repository->findPublishedByPublicId($rootId) !== null
    || $repository->findPublishedByPublicId($childId) !== null
    || $rootAfter?->status !== PageStatus::Draft
    || $childAfter?->status !== PageStatus::Draft
    || $rootAfter?->publishedAt !== null
    || $childAfter?->publishedAt !== null
) {
    fwrite(
        STDERR,
        "Снятие раздела не скрыло опубликованное поддерево.\n",
    );
    exit(1);
}

echo "Pages public API smoke OK\n";
