<?php

declare(strict_types=1);

$chairs = is_array($chairs ?? null) ? $chairs : [];
?>
<section class="content-section">
    <header class="content-heading">
        <p class="eyebrow">Образование</p>
        <h1>Кафедры</h1>
        <p>Учебные подразделения и преподавательский состав.</p>
    </header>

    <?php if ($chairs === []): ?>
        <p>Опубликованных кафедр пока нет.</p>
    <?php else: ?>
        <div class="content-list">
            <?php foreach ($chairs as $chair): ?>
                <article class="content-card">
                    <h2><a href="<?= $theme->e($chair['url']) ?>"><?= $theme->e($chair['name']) ?></a></h2>
                    <?php if (($chair['short_name'] ?? null) !== null): ?><p><?= $theme->e((string) $chair['short_name']) ?></p><?php endif; ?>
                    <p>Преподавателей: <?= (int) ($chair['teacher_count'] ?? 0) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
