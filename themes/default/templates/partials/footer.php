<?php declare(strict_types=1); ?>
<footer class="site-footer">
    <div class="container site-footer__inner">
        <div>
            <strong><?= $theme->e($siteName ?? 'ChurchCMS') ?></strong>
            <?php if (!empty($footerText)): ?>
                <p><?= $theme->e($footerText) ?></p>
            <?php endif; ?>
        </div>

        <p class="site-footer__meta">
            Работает на ChurchCMS
        </p>
    </div>
</footer>
