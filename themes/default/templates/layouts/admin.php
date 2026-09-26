<?php declare(strict_types=1);
$displayName = is_array($adminUser ?? null)
    ? (string) (($adminUser['display_name'] ?? null) ?: ($adminUser['username'] ?? ''))
    : '';
$currentSection = (string) ($adminSection ?? 'overview');
$current = static fn(string $section): string =>
    $currentSection === $section ? ' aria-current="page"' : '';
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
</head>
<body class="admin-app">
<a class="skip-link" href="#admin-content">Перейти к содержанию</a>
<div class="admin-frame">
    <aside class="admin-sidebar" aria-label="Управление сайтом">
        <a class="admin-brand" href="<?= $theme->e($theme->route('admin_dashboard')) ?>">
            <span class="admin-brand__mark" aria-hidden="true">CMS</span>
            <span><strong><?= $theme->e($siteName ?? 'ChurchCMS') ?></strong><small>Управление сайтом</small></span>
        </a>

        <nav class="admin-nav" aria-label="Разделы">
            <a href="<?= $theme->e($theme->route('admin_dashboard')) ?>"<?= $current('overview') ?>>Обзор</a>
            <?php if (!empty($canManagePublications)): ?>
                <a href="<?= $theme->e($theme->route('admin_publications')) ?>"<?= $current('publications') ?>>Публикации</a>
            <?php endif; ?>
            <?php if (!empty($canModerateComments)): ?>
                <a href="<?= $theme->e($theme->route('admin_comments')) ?>"<?= $current('comments') ?>>
                    Комментарии
                    <?php if ((int) ($pendingComments ?? 0) > 0): ?>
                        <span class="admin-nav__badge"><?= $theme->e((int) $pendingComments) ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </nav>

        <div class="admin-sidebar__footer">
            <?php if ($displayName !== ''): ?><span><?= $theme->e($displayName) ?></span><?php endif; ?>
            <form method="post" action="<?= $theme->e($theme->route('admin_logout')) ?>">
                <?= $theme->csrfInput() ?>
                <button class="admin-logout" type="submit">Выйти</button>
            </form>
        </div>
    </aside>

    <div class="admin-workspace">
        <header class="admin-topbar">
            <div>
                <strong><?= $theme->e($title ?? 'Управление') ?></strong>
                <?php if ($displayName !== ''): ?>
                    <span><?= $theme->e($displayName) ?></span>
                <?php endif; ?>
            </div>
            <a href="/" target="_blank" rel="noopener">Открыть сайт ↗</a>
        </header>
        <main class="admin-content" id="admin-content">
            <?= $content ?>
        </main>
    </div>
</div>
</body>
</html>
