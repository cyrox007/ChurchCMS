<?php declare(strict_types=1); $people = is_array($people ?? null) ? $people : []; ?>
<section class="content-section">
    <header class="section-heading"><p class="eyebrow">Люди</p><h1>Духовенство и сотрудники</h1></header>
    <?php if ($people === []): ?>
        <p>Карточек пока нет.</p>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($people as $person): ?>
                <article class="card">
                    <h2><a href="<?= $theme->e((string) ($person['url'] ?? '#')) ?>"><?= $theme->e((string) ($person['display_name'] ?? '')) ?></a></h2>
                    <?php foreach (($person['appointments'] ?? []) as $appointment): ?>
                        <p><?= $theme->e((string) ($appointment['title'] ?? '')) ?></p>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
