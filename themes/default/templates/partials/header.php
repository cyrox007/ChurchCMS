<?php declare(strict_types=1); ?>
<header class="site-header">
    <div class="site-header__backdrop" aria-hidden="true"></div>

    <div class="container site-header__inner">
        <div class="identity">
            <a class="identity__mark" href="<?= $theme->e($theme->route('home')) ?>" aria-label="На главную">
                <span aria-hidden="true">CMS</span>
            </a>

            <div class="identity__text">
                <a class="identity__name" href="<?= $theme->e($theme->route('home')) ?>">
                    <?= $theme->e($siteName ?? 'ChurchCMS') ?>
                </a>

                <?php if (!empty($siteSubtitle)): ?>
                    <p class="identity__subtitle"><?= $theme->e($siteSubtitle) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (is_array($navigation) && $navigation !== []): ?>
            <nav class="primary-nav" aria-label="Основная навигация">
                <ul>
                    <?php foreach ($navigation as $item): ?>
                        <?php
                        $label = is_array($item) ? (string) ($item['label'] ?? '') : '';
                        $url = is_array($item) ? (string) ($item['url'] ?? '') : '';
                        if ($label === '' || $url === '') {
                            continue;
                        }
                        ?>
                        <li><a href="<?= $theme->e($url) ?>"><?= $theme->e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</header>
