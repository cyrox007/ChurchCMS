<?php

declare(strict_types=1);
?>
<section class="content-section">
    <header class="content-heading">
        <p class="eyebrow">Расписание обучения</p>
        <h1><?= $theme->e($schedule['title']) ?></h1>
        <p><?= $theme->e($schedule['program_title']) ?></p>
    </header>
    <article class="content-card">
        <p><strong>Начало:</strong> <?= $theme->e($schedule['starts_at']) ?></p>
        <p><strong>Окончание:</strong> <?= $theme->e($schedule['ends_at']) ?></p>
        <p><strong>Часовой пояс:</strong> <?= $theme->e($schedule['timezone']) ?></p>
        <?php if ($schedule['location'] !== ''): ?><p><strong>Место:</strong> <?= $theme->e($schedule['location']) ?></p><?php endif; ?>
        <?php if (($schedule['note'] ?? '') !== ''): ?><p><?= nl2br($theme->e($schedule['note'])) ?></p><?php endif; ?>
    </article>
</section>
