<?php declare(strict_types=1); $ministry = is_array($ministry ?? null) ? $ministry : []; ?>
<article class="content-section">
    <header class="section-heading">
        <p class="eyebrow">Служение</p>
        <h1><?= $theme->e((string) ($ministry['title'] ?? 'Служение')) ?></h1>
        <?php if (($ministry['short_title'] ?? null) !== null): ?><p><?= $theme->e((string) $ministry['short_title']) ?></p><?php endif; ?>
    </header>
    <?php if (($ministry['leader_name'] ?? null) !== null): ?><p><strong>Руководитель:</strong> <?= $theme->e((string) $ministry['leader_name']) ?></p><?php endif; ?>
    <?php if (($ministry['contact_email'] ?? null) !== null): ?><p><strong>Email:</strong> <a href="mailto:<?= $theme->e((string) $ministry['contact_email']) ?>"><?= $theme->e((string) $ministry['contact_email']) ?></a></p><?php endif; ?>
    <?php if (($ministry['contact_phone'] ?? null) !== null): ?><p><strong>Телефон:</strong> <?= $theme->e((string) $ministry['contact_phone']) ?></p><?php endif; ?>
    <?php if (($ministry['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $ministry['summary']) ?></p><?php endif; ?>
    <?php if (($ministry['description_html'] ?? '') !== ''): ?><div class="prose"><?= (string) $ministry['description_html'] ?></div><?php endif; ?>
</article>
