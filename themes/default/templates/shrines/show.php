<?php declare(strict_types=1); $shrine = is_array($shrine ?? null) ? $shrine : []; $types = ['relics' => 'Мощи', 'icon' => 'Икона', 'place' => 'Святое место', 'object' => 'Священный предмет', 'spring' => 'Источник', 'other' => 'Другое']; ?>
<article class="content-section">
    <header class="section-heading">
        <p class="eyebrow"><?= $theme->e($types[(string) ($shrine['shrine_type'] ?? 'other')] ?? 'Святыня') ?></p>
        <h1><?= $theme->e((string) ($shrine['title'] ?? 'Святыня')) ?></h1>
        <?php if (($shrine['subtitle'] ?? null) !== null): ?><p><?= $theme->e((string) $shrine['subtitle']) ?></p><?php endif; ?>
    </header>
    <?php if (($shrine['location_name'] ?? null) !== null): ?><p><strong>Местонахождение:</strong> <?= $theme->e((string) $shrine['location_name']) ?></p><?php endif; ?>
    <?php if (($shrine['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $shrine['summary']) ?></p><?php endif; ?>
    <?php if (($shrine['description_html'] ?? '') !== ''): ?><div class="prose"><?= (string) $shrine['description_html'] ?></div><?php endif; ?>
</article>
