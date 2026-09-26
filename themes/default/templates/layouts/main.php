<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b315d">
    <meta name="color-scheme" content="light">
    <title><?= $theme->e($title ?? 'ChurchCMS') ?></title>
    <?= $theme->seoTags(is_array($seo ?? null) ? $seo : [], (string) ($title ?? 'ChurchCMS')) ?>
    <link rel="stylesheet" href="<?= $theme->e($theme->asset('css/site.css')) ?>">
    <script src="<?= $theme->e($theme->asset('js/share.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Перейти к содержанию</a>

<div class="page-shell">
    <?= $theme->partial('partial.header', [
        'siteName' => $siteName ?? 'ChurchCMS',
        'siteSubtitle' => $siteSubtitle ?? null,
        'navigation' => $navigation ?? [],
    ]) ?>

    <main class="site-main" id="main-content">
        <?= $content ?>
    </main>

    <?= $theme->partial('partial.footer', [
        'siteName' => $siteName ?? 'ChurchCMS',
        'footerText' => $footerText ?? null,
    ]) ?>
</div>
</body>
</html>
