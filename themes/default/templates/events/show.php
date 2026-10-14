<?php

declare(strict_types=1);

$event = is_array($event ?? null) ? $event : [];
$startsAt = trim((string) ($event['starts_at'] ?? ''));
$startsLabel = $startsAt;

if ($startsAt !== '') {
    try {
        $startsLabel = (new DateTimeImmutable($startsAt))->format('d.m.Y · H:i');
    } catch (Throwable) {
        $startsLabel = $startsAt;
    }
}
?>
<article class="article-shell">
    <header class="article-header">
        <p class="eyebrow">Событие</p>
        <h1><?= $theme->e((string) ($event['title'] ?? 'Событие')) ?></h1>
        <?php if ($startsLabel !== ''): ?>
            <p class="article-header__meta"><?= $theme->e($startsLabel) ?></p>
        <?php endif; ?>
    </header>

    <?php if (!empty($event['location_name'])): ?>
        <p class="article-fact">
            <strong>Место:</strong>
            <?= $theme->e((string) $event['location_name']) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($event['excerpt'])): ?>
        <p class="publication__lead">
            <?= $theme->e((string) $event['excerpt']) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($event['description'])): ?>
        <div class="prose">
            <p><?= nl2br($theme->e((string) $event['description'])) ?></p>
        </div>
    <?php endif; ?>
</article>
