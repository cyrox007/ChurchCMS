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

$navIcon = static function (string $id, string $group = ''): string {
    $icon = match (true) {
        $id === 'overview' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        $id === 'publications' => '<path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4"/><path d="M9 11h6M9 15h6"/>',
        in_array($id, ['publication_rubrics', 'publication-rubrics'], true) => '<path d="M4 6h6l2 2h8v11H4z"/><path d="M8 12h8M8 15h5"/>',
        str_contains($id, 'comment') => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/>',
        $id === 'library' => '<path d="M4 5a3 3 0 0 1 3-2h5v17H7a3 3 0 0 0-3 2z"/><path d="M20 5a3 3 0 0 0-3-2h-5v17h5a3 3 0 0 1 3 2z"/>',
        str_contains($id, 'page') => '<path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4"/>',
        str_contains($id, 'redirect') => '<path d="M5 7h9a5 5 0 0 1 5 5v5"/><path d="m15 14 4 4 4-4"/>',
        str_contains($id, 'document') => '<path d="M7 3h8l3 3v15H7z"/><path d="M10 11h5M10 15h5"/>',
        $id === 'people' => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><circle cx="17" cy="9" r="2"/><path d="M16 15a5 5 0 0 1 5 5"/>',
        $id === 'worship' => '<path d="M12 3v18M8 7h8"/><path d="M5 21h14"/>',
        $id === 'events' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        $id === 'ministries' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 12h18"/>',
        $id === 'shrines' => '<path d="M4 21h16M6 18h12M8 18V9h8v9M10 9V6h4v3M12 3v3"/>',
        $id === 'saints' => '<path d="m12 3 2.4 5 5.6.8-4 3.9.9 5.5-4.9-2.6-4.9 2.6.9-5.5-4-3.9 5.6-.8z"/>',
        in_array($id, ['sunday_school', 'sunday-school'], true) => '<path d="M4 5a3 3 0 0 1 3-2h5v17H7a3 3 0 0 0-3 2z"/><path d="M20 5a3 3 0 0 0-3-2h-5v17h5a3 3 0 0 1 3 2z"/><path d="M12 7v6M9 10h6"/>',
        str_contains($id, 'media') || str_contains($id, 'gallery') => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/>',
        str_contains($id, 'organization') || $group === 'Структура' => '<rect x="9" y="3" width="6" height="5"/><rect x="3" y="16" width="6" height="5"/><rect x="15" y="16" width="6" height="5"/><path d="M12 8v4M6 16v-4h12v4"/>',
        str_contains($id, 'system') || str_contains($id, 'operation') || $group === 'Система' => '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.4 1A7 7 0 0 0 15 6l-.3-2.6h-4L10.4 6A7 7 0 0 0 8 7.1l-2.4-1-2 3.4 2 1.5a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.4-1A7 7 0 0 0 10.4 18l.3 2.6h4L15 18a7 7 0 0 0 1.5-1.1l2.4 1 2-3.4-2-1.5a7 7 0 0 0 .1-1z"/>',
        $group === 'Интеграции' => '<path d="M8 12h8M12 8v8"/><path d="M6 3h12v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4zM9 21h6M12 12v9"/>',
        $group === 'Образование' => '<path d="m3 9 9-5 9 5-9 5z"/><path d="M7 12v5c3 2 7 2 10 0v-5"/>',
        default => '<circle cx="12" cy="12" r="8"/><path d="M9 12h6"/>',
    };

    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icon . '</svg>';
};
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
            <a href="<?= $theme->e($theme->route('admin_dashboard')) ?>"<?= $current('overview') ?>>
                <span class="admin-nav__label">
                    <span class="admin-nav__icon" aria-hidden="true"><?= $navIcon('overview') ?></span>
                    <span>Обзор</span>
                </span>
            </a>

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
                    <span class="admin-nav__label">
                        <span class="admin-nav__icon" aria-hidden="true"><?= $navIcon($entryId, $group) ?></span>
                        <span><?= $theme->e((string) $entry['label']) ?></span>
                    </span>
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
<script src="<?= $theme->e($theme->asset('js/admin-disclosure.js')) ?>" defer></script>
<script src="<?= $theme->e($theme->asset('js/admin-editor.js')) ?>" defer></script>
</body>
</html>
