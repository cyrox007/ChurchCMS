<?php declare(strict_types=1); $saints = is_array($saints ?? null) ? $saints : []; ?>
<section class="content-section">
    <header class="section-heading"><p class="eyebrow">Церковная жизнь</p><h1>Святые</h1></header>
    <?php if ($saints === []): ?><p>Опубликованных карточек пока нет.</p><?php else: ?>
        <div class="content-grid">
            <?php foreach ($saints as $saint): ?>
                <article class="content-card">
                    <?php if (($saint['saint_rank'] ?? null) !== null): ?><p class="eyebrow"><?= $theme->e((string) $saint['saint_rank']) ?></p><?php endif; ?>
                    <h2><a href="<?= $theme->e((string) ($saint['url'] ?? '#')) ?>"><?= $theme->e((string) ($saint['display_name'] ?? 'Святой')) ?></a></h2>
                    <?php if (($saint['commemoration_text'] ?? null) !== null): ?><p><strong>Дни памяти:</strong> <?= $theme->e((string) $saint['commemoration_text']) ?></p><?php endif; ?>
                    <?php if (($saint['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $saint['summary']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
