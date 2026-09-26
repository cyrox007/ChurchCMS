<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $theme->e($title ?? 'ChurchCMS') ?></title>
    <link rel="stylesheet" href="<?= $theme->e($theme->asset('css/site.css')) ?>">
</head>
<body>
<?= $theme->partial('partial.header', ['siteName' => $siteName ?? 'ChurchCMS']) ?>

<main class="site-main">
    <?= $content ?>
</main>

<?= $theme->partial('partial.footer') ?>
</body>
</html>
