<?php
declare(strict_types=1);

$displayName = is_array($adminUser ?? null)
    ? (string) (($adminUser['display_name'] ?? null) ?: ($adminUser['username'] ?? ''))
    : '';
$currentSection = (string) ($adminSection ?? 'overview');
$current = static fn(string $section): string =>
    $currentSection === $section ? ' aria-current="page"' : '';
$navigation = is_array($adminNavigation ?? null)
    ? $adminNavigation
    : [];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071d36">
    <meta name="color-scheme" content="light">
    <title><?= $theme->e($title ?? 'ChurchCMS') ?> — управление</title>
    <link rel="stylesheet" href="<?= $theme->e($theme->asset('css/site.css')) ?>">
    <link rel="stylesheet" href="<?= $theme->e($theme->asset('css/admin-async.css')) ?>">
    <link rel="stylesheet" href="<?= $theme->e($theme->asset('css/admin-modern.css')) ?>">
    <link rel="stylesheet" href="<?= $theme->e($theme->asset('css/admin-controls.css')) ?>">
</head>
<body class="admin-app">
<a class="skip-link" href="#admin-content">Перейти к содержанию</a>
<div class="admin-frame" data-admin-frame>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Управление сайтом">
        <a class="admin-brand" href="<?= $theme->e($theme->route('admin_dashboard')) ?>">
            <span class="admin-brand__mark" aria-hidden="true">CMS</span>
            <span class="admin-brand__text">
                <strong><?= $theme->e($siteName ?? 'ChurchCMS') ?></strong>
                <small><?= $theme->e($siteProfileLabel ?? 'Управление сайтом') ?></small>
            </span>
        </a>

        <nav class="admin-nav" aria-label="Разделы">
            <a href="<?= $theme->e($theme->route('admin_dashboard')) ?>"<?= $current('overview') ?>>Обзор</a>

            <?php $lastGroup = ''; ?>
            <?php foreach ($navigation as $entry): ?>
                <?php
                $entryId = (string) ($entry['id'] ?? '');
                $badge = (int) (($adminNavigationBadges[$entryId] ?? 0));
                $group = trim((string) ($entry['group'] ?? 'Дополнительно'));
                ?>
                <?php if ($group !== $lastGroup): ?>
                    <span class="admin-nav__group"><?= $theme->e($group) ?></span>
                    <?php $lastGroup = $group; ?>
                <?php endif; ?>
                <a
                    href="<?= $theme->e($theme->route((string) $entry['route'])) ?>"
                    <?= $current($entryId) ?>
                >
                    <span><?= $theme->e((string) $entry['label']) ?></span>
                    <?php if ($badge > 0): ?>
                        <span class="admin-nav__badge"><?= $theme->e($badge) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar__footer">
            <?php if ($displayName !== ''): ?>
                <a
                    class="admin-account-link"
                    href="<?= $theme->e($theme->route('admin_account_password')) ?>"
                    <?= $current('account') ?>
                >
                    <?= $theme->e($displayName) ?>
                </a>
            <?php endif; ?>
            <form method="post" action="<?= $theme->e($theme->route('admin_logout')) ?>" data-no-ajax>
                <?= $theme->csrfInput() ?>
                <button class="admin-logout" type="submit">Выйти</button>
            </form>
        </div>
    </aside>

    <div class="admin-workspace">
        <header class="admin-topbar">
            <button
                class="admin-sidebar-toggle"
                type="button"
                hidden
                aria-controls="admin-sidebar"
                aria-expanded="true"
                data-admin-sidebar-toggle
            >
                <span aria-hidden="true">☰</span>
                <span data-admin-sidebar-toggle-label>Скрыть меню</span>
            </button>

            <div class="admin-topbar__context">
                <strong><?= $theme->e($title ?? 'Управление') ?></strong>
                <?php if ($displayName !== ''): ?>
                    <span><?= $theme->e($displayName) ?></span>
                <?php endif; ?>
            </div>

            <form
                class="admin-global-search"
                method="get"
                action="<?= $theme->e($theme->route('admin_search')) ?>"
                role="search"
            >
                <label class="admin-global-search__field">
                    <span class="admin-visually-hidden">Поиск по управлению</span>
                    <input
                        type="search"
                        name="q"
                        value="<?= $theme->e($adminSearchQuery ?? '') ?>"
                        placeholder="Найти публикацию…"
                        minlength="2"
                        maxlength="100"
                        autocomplete="off"
                    >
                </label>
                <button type="submit">Найти</button>
            </form>

            <a class="admin-task-center-link" href="<?= $theme->e($theme->route('admin_tasks')) ?>">
                Задачи
                <?php if ((int) ($adminTaskCount ?? 0) > 0): ?>
                    <span class="admin-task-center-link__badge">
                        <?= $theme->e(min(99, (int) $adminTaskCount)) ?><?= (int) $adminTaskCount > 99 ? '+' : '' ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="/" target="_blank" rel="noopener">Открыть сайт ↗</a>
        </header>
        <main class="admin-content" id="admin-content">
            <?= $content ?>
        </main>
    </div>
</div>
<div
    class="admin-async-status"
    data-admin-async-status
    role="status"
    aria-live="polite"
    aria-atomic="true"
    hidden
></div>
<script src="<?= $theme->e($theme->asset('js/admin-shell.js')) ?>" defer></script>
<script src="<?= $theme->e($theme->asset('js/admin-async.js')) ?>" defer></script>
<script src="<?= $theme->e($theme->asset('js/admin-editor.js')) ?>" defer></script>
</body>
</html>
