<?php declare(strict_types=1);
$headerNavigation = is_array($navigation ?? null)
    ? array_slice($navigation, 0, 8)
    : [];
$today = new \DateTimeImmutable('now');
?>
<header class="site-header">
    <div class="site-servicebar">
        <div class="container site-servicebar__inner">
            <p class="site-servicebar__date">
                <?= $theme->e($today->format('d.m.Y')) ?>
            </p>
            <nav class="site-servicebar__links" aria-label="Служебная навигация">
                <a href="/publications">Новости и публикации</a>
                <a href="/search">Поиск</a>
                <a href="/admin">Администратору</a>
            </nav>
        </div>
    </div>

    <div class="site-header__visual">
        <div class="container site-header__inner">
            <div class="identity">
                <a class="identity__mark" href="<?= $theme->e($theme->route('home')) ?>" aria-label="На главную">
                    <span class="identity__mark-cross" aria-hidden="true"></span>
                </a>

                <div class="identity__text">
                    <a class="identity__name" href="<?= $theme->e($theme->route('home')) ?>">
                        <?= $theme->e($siteName ?? 'ChurchCMS') ?>
                    </a>

                    <p class="identity__subtitle">
                        <?= $theme->e(
                            !empty($siteSubtitle)
                                ? (string) $siteSubtitle
                                : 'Официальный сайт православной организации'
                        ) ?>
                    </p>
                </div>
            </div>

            <div class="site-header__quick" aria-label="Быстрый переход">
                <p class="site-header__quick-label">Актуальное</p>
                <a href="/publications">Последние новости</a>
                <small>События, объявления и материалы организации</small>
            </div>
        </div>
    </div>

    <?php if ($headerNavigation !== []): ?>
        <nav class="primary-nav" aria-label="Основная навигация">
            <div class="container primary-nav__inner">
                <ul>
                    <?php foreach ($headerNavigation as $item): ?>
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
            </div>
        </nav>
    <?php endif; ?>
</header>
