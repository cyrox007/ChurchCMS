<?php

declare(strict_types=1);

$schedules = is_array($schedules ?? null) ? $schedules : [];
?>
<section class="content-section">
    <header class="content-heading">
        <p class="eyebrow">Образование</p>
        <h1>Расписание обучения</h1>
        <p>Опубликованные занятия образовательных программ.</p>
    </header>

    <?php if ($schedules === []): ?>
        <p>Опубликованных занятий пока нет.</p>
    <?php else: ?>
        <div class="content-list">
            <?php foreach ($schedules as $schedule): ?>
                <article class="content-card">
                    <h2><a href="<?= $theme->e($schedule['url']) ?>"><?= $theme->e($schedule['title']) ?></a></h2>
                    <p><strong><?= $theme->e($schedule['program_title']) ?></strong></p>
                    <p><?= $theme->e($schedule['starts_at']) ?> — <?= $theme->e($schedule['ends_at']) ?></p>
                    <?php if ($schedule['location'] !== ''): ?><p><?= $theme->e($schedule['location']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
