<?php declare(strict_types=1);
$publicNavigation = is_array($navigation ?? null)
    ? $navigation
    : [];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#08365f">
    <meta name="color-scheme" content="light">
    <title><?= $theme->e($title ?? 'ChurchCMS') ?></title>
    <?= $theme->seoTags(is_array($seo ?? null) ? $seo : [], (string) ($title ?? 'ChurchCMS')) ?>
    <link rel="stylesheet" href="<?= $theme->e($theme->asset('css/site.css')) ?>">
    <link rel="stylesheet" href="<?= $theme->e($theme->asset('css/public-modern.css')) ?>">
    <script src="<?= $theme->e($theme->asset('js/share.js')) ?>" defer></script>
</head>
<body class="public-theme">
<a class="skip-link" href="#main-content">Перейти к содержанию</a>

<div class="page-shell">
    <?= $theme->partial('partial.header', [
        'siteName' => $siteName ?? 'ChurchCMS',
        'siteSubtitle' => $siteSubtitle ?? null,
        'navigation' => $publicNavigation,
    ]) ?>

    <div class="public-frame">
        <?php if ($publicNavigation !== []): ?>
            <aside class="section-nav" aria-label="Разделы сайта">
                <p class="section-nav__title">Разделы сайта</p>
                <nav>
                    <ul>
                        <?php foreach ($publicNavigation as $item): ?>
                            <?php
                            $label = is_array($item)
                                ? trim((string) ($item['label'] ?? ''))
                                : '';
                            $url = is_array($item)
                                ? trim((string) ($item['url'] ?? ''))
                                : '';
                            if ($label === '' || $url === '') {
                                continue;
                            }
                            ?>
                            <li>
                                <a href="<?= $theme->e($url) ?>">
                                    <?= $theme->e($label) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <nav class="section-nav__service" aria-label="Служебные разделы">
                    <ul>
                        <li><a href="/publications">Все публикации</a></li>
                        <li><a href="/search">Поиск по сайту</a></li>
                    </ul>
                </nav>
            </aside>

            <details class="section-nav-mobile">
                <summary>Разделы сайта</summary>
                <nav aria-label="Разделы сайта на мобильном устройстве">
                    <ul>
                        <?php foreach ($publicNavigation as $item): ?>
                            <?php
                            $label = is_array($item)
                                ? trim((string) ($item['label'] ?? ''))
                                : '';
                            $url = is_array($item)
                                ? trim((string) ($item['url'] ?? ''))
                                : '';
                            if ($label === '' || $url === '') {
                                continue;
                            }
                            ?>
                            <li>
                                <a href="<?= $theme->e($url) ?>">
                                    <?= $theme->e($label) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </details>
        <?php endif; ?>

        <main class="site-main" id="main-content">
            <?= $content ?>
        </main>
    </div>

    <?= $theme->partial('partial.footer', [
        'siteName' => $siteName ?? 'ChurchCMS',
        'footerText' => $footerText ?? null,
    ]) ?>
</div>
</body>
</html>
