<?php declare(strict_types=1); $saint = is_array($saint ?? null) ? $saint : []; ?>
<article class="content-section">
    <header class="section-heading">
        <?php if (($saint['saint_rank'] ?? null) !== null): ?><p class="eyebrow"><?= $theme->e((string) $saint['saint_rank']) ?></p><?php endif; ?>
        <h1><?= $theme->e((string) ($saint['display_name'] ?? 'Святой')) ?></h1>
        <?php if (($saint['secular_name'] ?? null) !== null): ?><p>Мирское имя: <?= $theme->e((string) $saint['secular_name']) ?></p><?php endif; ?>
    </header>
    <?php if (($saint['commemoration_text'] ?? null) !== null): ?><p><strong>Дни памяти:</strong> <?= $theme->e((string) $saint['commemoration_text']) ?></p><?php endif; ?>
    <?php if (($saint['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $saint['summary']) ?></p><?php endif; ?>
    <?php if (($saint['biography_html'] ?? '') !== ''): ?><div class="prose"><?= (string) $saint['biography_html'] ?></div><?php endif; ?>
</article>
