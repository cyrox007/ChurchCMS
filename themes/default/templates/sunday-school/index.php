<?php declare(strict_types=1); $schools = is_array($schools ?? null) ? $schools : []; ?>
<section class="content-section">
    <header class="section-heading"><p class="eyebrow">Образование и приходская жизнь</p><h1>Воскресная школа</h1></header>
    <?php if ($schools === []): ?><p>Опубликованных воскресных школ пока нет.</p><?php else: ?>
        <div class="content-grid">
            <?php foreach ($schools as $school): ?>
                <article class="content-card">
                    <h2><a href="<?= $theme->e((string) ($school['url'] ?? '#')) ?>"><?= $theme->e((string) ($school['title'] ?? 'Воскресная школа')) ?></a></h2>
                    <?php if (($school['age_info'] ?? null) !== null): ?><p><strong>Возраст:</strong> <?= $theme->e((string) $school['age_info']) ?></p><?php endif; ?>
                    <?php if (($school['leader_name'] ?? null) !== null): ?><p><strong>Руководитель:</strong> <?= $theme->e((string) $school['leader_name']) ?></p><?php endif; ?>
                    <?php if (($school['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $school['summary']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
