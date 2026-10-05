<?php declare(strict_types=1); $ministries = is_array($ministries ?? null) ? $ministries : []; ?>
<section class="content-section">
    <header class="section-heading"><p class="eyebrow">Церковная жизнь</p><h1>Служения и отделы</h1></header>
    <?php if ($ministries === []): ?><p>Опубликованных служений пока нет.</p><?php else: ?>
        <div class="content-grid">
            <?php foreach ($ministries as $ministry): ?>
                <article class="content-card">
                    <h2><a href="<?= $theme->e((string) ($ministry['url'] ?? '#')) ?>"><?= $theme->e((string) ($ministry['title'] ?? 'Служение')) ?></a></h2>
                    <?php if (($ministry['leader_name'] ?? null) !== null): ?><p><strong>Руководитель:</strong> <?= $theme->e((string) $ministry['leader_name']) ?></p><?php endif; ?>
                    <?php if (($ministry['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $ministry['summary']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
