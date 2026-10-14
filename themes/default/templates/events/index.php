<?php

declare(strict_types=1);

$events = is_array($events ?? null) ? $events : [];
$calendar = is_array($calendar ?? null)
    ? $calendar
    : ['month' => '', 'days' => []];
$days = is_array($calendar['days'] ?? null)
    ? $calendar['days']
    : [];
?>
<section class="content-section">
    <header class="section-heading">
        <p class="eyebrow">Календарь</p>
        <h1>События</h1>
        <?php if (!empty($calendar['month'])): ?>
            <p>События за <?= $theme->e((string) $calendar['month']) ?>.</p>
        <?php endif; ?>
    </header>

    <?php if ($days === []): ?>
        <div class="empty-state">
            <h2>Ближайших событий пока нет</h2>
            <p>Новые события появятся здесь после публикации.</p>
        </div>
    <?php else: ?>
        <div class="event-calendar">
            <?php foreach ($days as $date => $dayEvents): ?>
                <?php if (!is_array($dayEvents) || $dayEvents === []): ?>
                    <?php continue; ?>
                <?php endif; ?>

                <section class="event-day">
                    <header class="event-day__date">
                        <span><?= $theme->e((string) $date) ?></span>
                    </header>

                    <div class="event-day__items">
                        <?php foreach ($dayEvents as $event): ?>
                            <article class="event-card">
                                <h2>
                                    <a href="<?= $theme->e((string) ($event['url'] ?? '#')) ?>">
                                        <?= $theme->e((string) ($event['title'] ?? 'Событие')) ?>
                                    </a>
                                </h2>
                                <?php if (!empty($event['location_name'])): ?>
                                    <p><?= $theme->e((string) $event['location_name']) ?></p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
