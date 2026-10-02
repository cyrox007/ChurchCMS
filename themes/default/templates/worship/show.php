<?php declare(strict_types=1); $service = is_array($service ?? null) ? $service : []; ?>
<article class="article-shell">
    <header class="article-header">
        <p class="eyebrow"><?= $theme->e((string) ($service['starts_at'] ?? '')) ?> UTC</p>
        <h1><?= $theme->e((string) ($service['title'] ?? 'Богослужение')) ?></h1>
        <?php if (($service['status'] ?? '') === 'cancelled'): ?><p><strong>Богослужение отменено</strong></p><?php endif; ?>
    </header>
    <?php if (!empty($service['location_name'])): ?><p><?= $theme->e((string) $service['location_name']) ?></p><?php endif; ?>
    <?php if (!empty($service['description'])): ?><p><?= nl2br($theme->e((string) $service['description'])) ?></p><?php endif; ?>
</article>
