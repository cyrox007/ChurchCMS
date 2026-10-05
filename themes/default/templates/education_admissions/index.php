<?php

declare(strict_types=1);

$admissions = is_array($admissions ?? null) ? $admissions : [];
?>
<section class="content-section">
    <header class="content-heading">
        <p class="eyebrow">Образование</p>
        <h1>Приёмная кампания</h1>
        <p>Сроки приёма, количество мест и условия поступления на образовательные программы.</p>
    </header>

    <?php if ($admissions === []): ?>
        <p>Опубликованных приёмных кампаний пока нет.</p>
    <?php else: ?>
        <div class="content-list">
            <?php foreach ($admissions as $admission): ?>
                <article class="content-card">
                    <h2><a href="<?= $theme->e($admission['url']) ?>"><?= $theme->e($admission['title']) ?></a></h2>
                    <p><a href="<?= $theme->e($admission['program_url']) ?>"><?= $theme->e($admission['program_title']) ?></a></p>
                    <p><strong>Учебный год:</strong> <?= $theme->e($admission['academic_year']) ?></p>
                    <?php if ($admission['starts_on'] !== null || $admission['ends_on'] !== null): ?>
                        <p>Приём: <?= $theme->e($admission['starts_on'] ?? '—') ?> — <?= $theme->e($admission['ends_on'] ?? '—') ?></p>
                    <?php endif; ?>
                    <p>Бюджетных мест: <?= (int) $admission['budget_seats'] ?> · платных мест: <?= (int) $admission['paid_seats'] ?></p>
                    <?php if ($admission['tuition_note'] !== ''): ?><p><?= $theme->e($admission['tuition_note']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
