<?php declare(strict_types=1); $events = is_array($events ?? null) ? $events : []; $calendar = is_array($calendar ?? null) ? $calendar : ['month' => '', 'days' => []]; ?>
<section class="content-section">
    <header class="section-heading"><p class="eyebrow">Календарь</p><h1>События</h1></header>
    <p>Месяц: <?= $theme->e((string) ($calendar['month'] ?? '')) ?></p>
    <?php foreach (($calendar['days'] ?? []) as $date => $dayEvents): ?>
        <section><h2><?= $theme->e((string) $date) ?></h2>
            <?php foreach ($dayEvents as $event): ?>
                <p><a href="<?= $theme->e((string) ($event['url'] ?? '#')) ?>"><?= $theme->e((string) ($event['title'] ?? 'Событие')) ?></a></p>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
    <?php if ($events === []): ?><p>Ближайших опубликованных событий пока нет.</p><?php endif; ?>
</section>
