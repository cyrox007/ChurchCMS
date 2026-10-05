<?php

declare(strict_types=1);

$programs = is_array($programs ?? null) ? $programs : [];
?>
<section class="content-section">
    <header class="content-heading">
        <p class="eyebrow">Образование</p>
        <h1>Образовательные программы</h1>
        <p>Действующие программы обучения, формы и сроки обучения.</p>
    </header>

    <?php if ($programs === []): ?>
        <p>Опубликованных образовательных программ пока нет.</p>
    <?php else: ?>
        <div class="content-list">
            <?php foreach ($programs as $program): ?>
                <article class="content-card">
                    <h2><a href="<?= $theme->e($program['url']) ?>"><?= $theme->e($program['title']) ?></a></h2>
                    <p><strong><?= $theme->e($program['education_level']) ?></strong> · <?= $theme->e($program['study_form']) ?></p>
                    <?php if ($program['duration_months'] !== null): ?><p>Длительность: <?= (int) $program['duration_months'] ?> мес.</p><?php endif; ?>
                    <?php if ($program['qualification'] !== null): ?><p>Квалификация: <?= $theme->e($program['qualification']) ?></p><?php endif; ?>
                    <?php if ($program['summary'] !== ''): ?><p><?= $theme->e($program['summary']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
