<?php declare(strict_types=1); ?>
<footer class="site-footer">
    <div class="container site-footer__inner">
        <div>
            <strong><?= $theme->e($siteName ?? 'ChurchCMS') ?></strong>
            <p>
                <?= $theme->e(
                    !empty($footerText)
                        ? (string) $footerText
                        : 'Официальный сайт организации'
                ) ?>
            </p>
            <nav class="site-footer__links" aria-label="Служебные ссылки">
                <a href="/publications">Публикации</a>
                <a href="/search">Поиск</a>
                <a href="/admin">Администратору</a>
            </nav>
        </div>

        <p class="site-footer__meta">
            Работает на ChurchCMS
        </p>
    </div>
</footer>
