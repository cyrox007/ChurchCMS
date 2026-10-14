<?php

declare(strict_types=1);

$services = is_array($services ?? null) ? $services : [];
?>
<section class="content-section">
    <header class="section-heading">
        <p class="eyebrow">Расписание</p>
        <h1>Богослужения</h1>
        <p>Ближайшие богослужения и изменения в расписании.</p>
    </header>

    <?php if ($services === []): ?>
        <div class="empty-state">
            <h2>Ближайших богослужений пока нет</h2>
            <p>Расписание появится здесь после публикации.</p>
        </div>
    <?php else: ?>
        <div class="service-list">
            <?php foreach ($services as $service): ?>
                <?php
                $startsAt = trim((string) ($service['starts_at'] ?? ''));
                $date = $startsAt;
                $time = '';

                if ($startsAt !== '') {
                    try {
                        $moment = new DateTimeImmutable($startsAt);
                        $date = $moment->format('d.m.Y');
                        $time = $moment->format('H:i');
                    } catch (Throwable) {
                        $date = $startsAt;
                    }
                }
                ?>
                <article class="service-card">
                    <div class="service-card__time">
                        <strong><?= $theme->e($date) ?></strong>
                        <?php if ($time !== ''): ?>
                            <span><?= $theme->e($time) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="service-card__content">
                        <h2>
                            <a href="<?= $theme->e((string) ($service['url'] ?? '#')) ?>">
                                <?= $theme->e((string) ($service['title'] ?? 'Богослужение')) ?>
                            </a>
                        </h2>

                        <?php if (!empty($service['location_name'])): ?>
                            <p><?= $theme->e((string) $service['location_name']) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (($service['status'] ?? '') === 'cancelled'): ?>
                        <strong class="service-card__status">Отменено</strong>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
