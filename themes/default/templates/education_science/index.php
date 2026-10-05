<?php

declare(strict_types=1);

$activities = is_array($activities ?? null) ? $activities : [];
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
        <p class="eyebrow">Образование</p>
        <h1>Научная деятельность</h1>
        <p>Исследования, научные проекты, публикации и мероприятия.</p>
    </header>

    <?php if ($activities === []): ?>
        <p>Опубликованных материалов пока нет.</p>
    <?php else: ?>
        <div class="content-list">
            <?php foreach ($activities as $activity): ?>
                <article class="content-card">
                    <p class="eyebrow"><?= $theme->e($types[$activity['activity_type']] ?? $activity['activity_type']) ?></p>
                    <h2><a href="<?= $theme->e($activity['url']) ?>"><?= $theme->e($activity['title']) ?></a></h2>
                    <?php if ($activity['starts_on'] !== null): ?><p><?= $theme->e($activity['starts_on']) ?><?php if ($activity['ends_on'] !== null): ?> — <?= $theme->e($activity['ends_on']) ?><?php endif; ?></p><?php endif; ?>
                    <?php if ($activity['summary'] !== ''): ?><p><?= $theme->e($activity['summary']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
