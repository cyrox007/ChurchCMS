<?php

declare(strict_types=1);

$menuItems = is_array($menuItems ?? null)
    ? $menuItems
    : [];
$pages = is_array($pages ?? null)
    ? $pages
    : [];
$systemRoutes = is_array($systemRoutes ?? null)
    ? $systemRoutes
    : [];
$status = is_array($navigationStatus ?? null)
    ? $navigationStatus
    : null;

$typeLabels = [
    'page' => 'Страница',
    'route' => 'Раздел сайта',
    'external' => 'Внешняя ссылка',
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Структура сайта</p>
            <h1>Основное меню</h1>
            <p>
                Добавляйте опубликованные страницы, системные разделы или
                внешние HTTPS-ссылки. Порядок определяется числом.
            </p>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'empty'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <section
        class="admin-operations-section"
        aria-labelledby="navigation-create-title"
    >
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Новый пункт</p>
                <h2 id="navigation-create-title">Добавить в меню</h2>
            </div>
        </div>

        <form
            class="admin-update-apply"
            method="post"
            action="<?= $theme->e($theme->route('admin_navigation_item_create')) ?>"
        >
            <?= $theme->csrfInput() ?>

            <label>
                <span>Название пункта</span>
                <input
                    type="text"
                    name="label"
                    maxlength="255"
                    required
                    autocomplete="off"
                >
            </label>

            <label>
                <span>Тип ссылки</span>
                <select name="item_type" required>
                    <option value="page">Страница</option>
                    <option value="route">Раздел сайта</option>
                    <option value="external">Внешняя HTTPS-ссылка</option>
                </select>
            </label>

            <label>
                <span>Страница</span>
                <select name="page_public_id">
                    <option value="">Не выбрана</option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?= $theme->e($page->publicId) ?>">
                            <?= $theme->e($page->navigationTitle ?? $page->title) ?>
                            · <?= $theme->e($page->path) ?>
                            <?php if ($page->status !== ChurchCMSModulesPagesPageStatus::Published): ?>
                                · черновик
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>
                    Черновик можно выбрать заранее, но на сайте он появится
                    только после публикации страницы.
                </small>
            </label>

            <label>
                <span>Системный раздел</span>
                <select name="route_name">
                    <option value="">Не выбран</option>
                    <?php foreach ($systemRoutes as $routeName => $label): ?>
                        <option value="<?= $theme->e((string) $routeName) ?>">
                            <?= $theme->e((string) $label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Внешний URL</span>
                <input
                    type="url"
                    name="external_url"
                    maxlength="1000"
                    placeholder="https://example.org/"
                    autocomplete="url"
                >
            </label>

            <label>
                <span>Порядок</span>
                <input
                    type="number"
                    name="sort_order"
                    min="-100000"
                    max="100000"
                    value="0"
                    inputmode="numeric"
                >
            </label>

            <label class="choice">
                <input
                    type="checkbox"
                    name="enabled"
                    value="1"
                    checked
                >
                <span>Показывать пункт</span>
            </label>

            <button
                class="button button--primary"
                type="submit"
            >
                Добавить
            </button>
        </form>
    </section>

    <section
        class="admin-operations-section"
        aria-labelledby="navigation-items-title"
    >
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Текущее меню</p>
                <h2 id="navigation-items-title">Пункты</h2>
            </div>
        </div>

        <?php if ($menuItems === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Меню пока пустое',
                'message' => 'Добавьте первый пункт через форму выше.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($menuItems as $item): ?>
                    <article
                        class="admin-operations-row"
                        role="listitem"
                    >
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($item->label) ?></strong>
                            <span>
                                <?= $theme->e($typeLabels[$item->itemType] ?? $item->itemType) ?>
                                · порядок: <?= $theme->e($item->sortOrder) ?>
                                · <?= $item->enabled ? 'показывается' : 'скрыт' ?>
                            </span>
                        </div>

                        <form
                            class="admin-update-apply"
                            method="post"
                            action="<?= $theme->e($theme->route(
                                'admin_navigation_item_update',
                                ['publicId' => $item->publicId],
                            )) ?>"
                        >
                            <?= $theme->csrfInput() ?>

                            <label>
                                <span>Название</span>
                                <input
                                    type="text"
                                    name="label"
                                    maxlength="255"
                                    required
                                    value="<?= $theme->e($item->label) ?>"
                                >
                            </label>

                            <label>
                                <span>Тип ссылки</span>
                                <select name="item_type" required>
                                    <?php foreach ($typeLabels as $type => $label): ?>
                                        <option
                                            value="<?= $theme->e($type) ?>"
                                            <?= $item->itemType === $type ? 'selected' : '' ?>
                                        >
                                            <?= $theme->e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>Страница</span>
                                <select name="page_public_id">
                                    <option value="">Не выбрана</option>
                                    <?php foreach ($pages as $page): ?>
                                        <option
                                            value="<?= $theme->e($page->publicId) ?>"
                                            <?= $item->pagePublicId === $page->publicId ? 'selected' : '' ?>
                                        >
                                            <?= $theme->e($page->navigationTitle ?? $page->title) ?>
                                            · <?= $theme->e($page->path) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>Системный раздел</span>
                                <select name="route_name">
                                    <option value="">Не выбран</option>
                                    <?php foreach ($systemRoutes as $routeName => $routeLabel): ?>
                                        <option
                                            value="<?= $theme->e((string) $routeName) ?>"
                                            <?= $item->routeName === $routeName ? 'selected' : '' ?>
                                        >
                                            <?= $theme->e((string) $routeLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>Внешний URL</span>
                                <input
                                    type="url"
                                    name="external_url"
                                    maxlength="1000"
                                    value="<?= $theme->e($item->externalUrl ?? '') ?>"
                                >
                            </label>

                            <label>
                                <span>Порядок</span>
                                <input
                                    type="number"
                                    name="sort_order"
                                    min="-100000"
                                    max="100000"
                                    value="<?= $theme->e($item->sortOrder) ?>"
                                >
                            </label>

                            <label class="choice">
                                <input
                                    type="checkbox"
                                    name="enabled"
                                    value="1"
                                    <?= $item->enabled ? 'checked' : '' ?>
                                >
                                <span>Показывать пункт</span>
                            </label>

                            <button
                                class="button button--primary"
                                type="submit"
                            >
                                Сохранить
                            </button>
                        </form>

                        <form
                            method="post"
                            action="<?= $theme->e($theme->route(
                                'admin_navigation_item_delete',
                                ['publicId' => $item->publicId],
                            )) ?>"
                        >
                            <?= $theme->csrfInput() ?>
                            <button
                                class="button button--quiet"
                                type="submit"
                            >
                                Удалить
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
