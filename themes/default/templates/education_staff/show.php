<?php

declare(strict_types=1);

$chair = is_array($chair ?? null) ? $chair : [];
$teachers = is_array($chair['teachers'] ?? null) ? $chair['teachers'] : [];
?>
<article class="content-section">
    <header class="content-heading">
        <p class="eyebrow">Кафедра</p>
        <h1><?= $theme->e((string) ($chair['name'] ?? '')) ?></h1>
        <?php if (($chair['short_name'] ?? null) !== null): ?><p><?= $theme->e((string) $chair['short_name']) ?></p><?php endif; ?>
    </header>

    <?php if (($chair['description_html'] ?? '') !== ''): ?>
        <section class="rich-text"><?= (string) $chair['description_html'] ?></section>
    <?php endif; ?>

    <section>
        <h2>Преподаватели</h2>
        <?php if ($teachers === []): ?>
            <p>Преподавательский состав пока не опубликован.</p>
        <?php else: ?>
            <div class="content-list">
                <?php foreach ($teachers as $teacher): ?>
                    <article class="content-card">
                        <h3><a href="<?= $theme->e((string) $teacher['url']) ?>"><?= $theme->e((string) $teacher['display_name']) ?></a></h3>
                        <p><?= $theme->e((string) $teacher['position_title']) ?></p>
                        <?php if (($teacher['academic_degree'] ?? null) !== null): ?><p>Учёная степень: <?= $theme->e((string) $teacher['academic_degree']) ?></p><?php endif; ?>
                        <?php if (($teacher['academic_title'] ?? null) !== null): ?><p>Учёное звание: <?= $theme->e((string) $teacher['academic_title']) ?></p><?php endif; ?>
                        <?php if (($teacher['disciplines'] ?? '') !== ''): ?><p>Дисциплины: <?= nl2br($theme->e((string) $teacher['disciplines'])) ?></p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</article>
