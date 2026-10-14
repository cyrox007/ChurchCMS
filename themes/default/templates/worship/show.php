<?php

declare(strict_types=1);

$service = is_array($service ?? null) ? $service : [];
$startsAt = trim((string) ($service['starts_at'] ?? ''));
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
        <p class="eyebrow">Богослужение</p>
        <h1><?= $theme->e((string) ($service['title'] ?? 'Богослужение')) ?></h1>
        <?php if ($startsLabel !== ''): ?>
            <p class="article-header__meta"><?= $theme->e($startsLabel) ?></p>
        <?php endif; ?>
        <?php if (($service['status'] ?? '') === 'cancelled'): ?>
            <p class="article-alert"><strong>Богослужение отменено</strong></p>
        <?php endif; ?>
    </header>

    <?php if (!empty($service['location_name'])): ?>
        <p class="article-fact">
            <strong>Место:</strong>
            <?= $theme->e((string) $service['location_name']) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($service['description'])): ?>
        <div class="prose">
            <p><?= nl2br($theme->e((string) $service['description'])) ?></p>
        </div>
    <?php endif; ?>
</article>
