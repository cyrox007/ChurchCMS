<?php declare(strict_types=1); $person = is_array($person ?? null) ? $person : []; ?>
<article class="article-shell">
    <?php $portrait = is_array($person['portrait'] ?? null) ? $person['portrait'] : null; ?>
    <header class="article-header">
        <?php if ($portrait !== null && !empty($portrait['url'])): ?>
            <img
                src="<?= $theme->e((string) $portrait['url']) ?>"
                alt="<?= $theme->e((string) ($portrait['title'] ?? ($person['display_name'] ?? ''))) ?>"
            >
        <?php endif; ?>
        <p class="eyebrow">Люди</p>
        <h1><?= $theme->e((string) ($person['display_name'] ?? '')) ?></h1>
    </header>
    <?php if (!empty($person['biography'])): ?><p><?= nl2br($theme->e((string) $person['biography'])) ?></p><?php endif; ?>
    <?php if (!empty($person['appointments'])): ?>
        <section><h2>Назначения</h2>
            <?php foreach ($person['appointments'] as $appointment): ?>
                <p><strong><?= $theme->e((string) ($appointment['title'] ?? '')) ?></strong>
                <?php if (!empty($appointment['started_on'])): ?> · с <?= $theme->e((string) $appointment['started_on']) ?><?php endif; ?>
                <?php if (!empty($appointment['ended_on'])): ?> по <?= $theme->e((string) $appointment['ended_on']) ?><?php endif; ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</article>
