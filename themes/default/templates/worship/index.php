<?php declare(strict_types=1); $services = is_array($services ?? null) ? $services : []; ?>
<section class="content-section">
    <header class="section-heading"><p class="eyebrow">Расписание</p><h1>Богослужения</h1></header>
    <?php if ($services === []): ?>
        <p>Ближайших богослужений пока нет.</p>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($services as $service): ?>
                <article class="card">
                    <p class="eyebrow"><?= $theme->e((string) ($service['starts_at'] ?? '')) ?> UTC</p>
                    <h2><a href="<?= $theme->e((string) ($service['url'] ?? '#')) ?>"><?= $theme->e((string) ($service['title'] ?? 'Богослужение')) ?></a></h2>
                    <?php if (($service['status'] ?? '') === 'cancelled'): ?><p><strong>Отменено</strong></p><?php endif; ?>
                    <?php if (!empty($service['location_name'])): ?><p><?= $theme->e((string) $service['location_name']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
