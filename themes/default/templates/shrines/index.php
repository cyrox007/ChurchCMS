<?php declare(strict_types=1); $shrines = is_array($shrines ?? null) ? $shrines : []; $types = ['relics' => 'Мощи', 'icon' => 'Икона', 'place' => 'Святое место', 'object' => 'Священный предмет', 'spring' => 'Источник', 'other' => 'Другое']; ?>
<section class="content-section">
    <header class="section-heading"><p class="eyebrow">Церковная жизнь</p><h1>Святыни</h1></header>
    <?php if ($shrines === []): ?><p>Опубликованных святынь пока нет.</p><?php else: ?>
        <div class="content-grid">
            <?php foreach ($shrines as $shrine): ?>
                <article class="content-card">
                    <p class="eyebrow"><?= $theme->e($types[(string) ($shrine['shrine_type'] ?? 'other')] ?? 'Святыня') ?></p>
                    <h2><a href="<?= $theme->e((string) ($shrine['url'] ?? '#')) ?>"><?= $theme->e((string) ($shrine['title'] ?? 'Святыня')) ?></a></h2>
                    <?php if (($shrine['location_name'] ?? null) !== null): ?><p><?= $theme->e((string) $shrine['location_name']) ?></p><?php endif; ?>
                    <?php if (($shrine['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $shrine['summary']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
