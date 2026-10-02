<?php declare(strict_types=1); $event = is_array($event ?? null) ? $event : []; ?>
<article class="article-shell">
    <header class="article-header">
        <p class="eyebrow"><?= $theme->e((string) ($event['starts_at'] ?? '')) ?> UTC</p>
        <h1><?= $theme->e((string) ($event['title'] ?? 'Событие')) ?></h1>
    </header>
    <?php if (!empty($event['location_name'])): ?><p><?= $theme->e((string) $event['location_name']) ?></p><?php endif; ?>
    <?php if (!empty($event['excerpt'])): ?><p><?= $theme->e((string) $event['excerpt']) ?></p><?php endif; ?>
    <?php if (!empty($event['description'])): ?><p><?= nl2br($theme->e((string) $event['description'])) ?></p><?php endif; ?>
</article>
