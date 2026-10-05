<?php

declare(strict_types=1);

$types = [
    'research' => 'Исследование',
    'conference' => 'Конференция',
    'publication' => 'Публикация',
    'grant' => 'Грант',
    'laboratory' => 'Лаборатория',
    'other' => 'Другое',
];
?>
<section class="content-section">
    <header class="content-heading">
        <p class="eyebrow"><?= $theme->e($types[$activity['activity_type']] ?? $activity['activity_type']) ?></p>
        <h1><?= $theme->e($activity['title']) ?></h1>
        <?php if ($activity['summary'] !== ''): ?><p><?= $theme->e($activity['summary']) ?></p><?php endif; ?>
    </header>
    <article class="content-card">
        <?php if ($activity['starts_on'] !== null): ?><p><strong>Период:</strong> <?= $theme->e($activity['starts_on']) ?><?php if ($activity['ends_on'] !== null): ?> — <?= $theme->e($activity['ends_on']) ?><?php endif; ?></p><?php endif; ?>
        <?= $activity['description_html'] ?>
        <?php if ($activity['external_url'] !== null): ?><p><a href="<?= $theme->e($activity['external_url']) ?>" rel="noopener noreferrer">Внешний источник</a></p><?php endif; ?>
    </article>
</section>
