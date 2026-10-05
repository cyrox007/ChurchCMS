<?php

declare(strict_types=1);

$admission = is_array($admission ?? null) ? $admission : [];
?>
<section class="content-section">
    <header class="content-heading">
        <p class="eyebrow">Приёмная кампания</p>
        <h1><?= $theme->e($admission['title'] ?? '') ?></h1>
        <p><a href="<?= $theme->e($admission['program_url'] ?? '#') ?>"><?= $theme->e($admission['program_title'] ?? '') ?></a></p>
    </header>

    <div class="content-card">
        <p><strong>Учебный год:</strong> <?= $theme->e($admission['academic_year'] ?? '') ?></p>
        <p><strong>Сроки приёма:</strong> <?= $theme->e($admission['starts_on'] ?? '—') ?> — <?= $theme->e($admission['ends_on'] ?? '—') ?></p>
        <p><strong>Бюджетных мест:</strong> <?= (int) ($admission['budget_seats'] ?? 0) ?></p>
        <p><strong>Платных мест:</strong> <?= (int) ($admission['paid_seats'] ?? 0) ?></p>
        <?php if (($admission['tuition_note'] ?? '') !== ''): ?><p><strong>Стоимость обучения:</strong> <?= $theme->e($admission['tuition_note']) ?></p><?php endif; ?>
        <?php if (($admission['requirements'] ?? '') !== ''): ?><h2>Требования</h2><p><?= nl2br($theme->e($admission['requirements'])) ?></p><?php endif; ?>
        <?php if (($admission['entrance_tests'] ?? '') !== ''): ?><h2>Вступительные испытания</h2><p><?= nl2br($theme->e($admission['entrance_tests'])) ?></p><?php endif; ?>
        <?php if (($admission['contact_note'] ?? '') !== ''): ?><h2>Контакты</h2><p><?= nl2br($theme->e($admission['contact_note'])) ?></p><?php endif; ?>
    </div>
</section>
