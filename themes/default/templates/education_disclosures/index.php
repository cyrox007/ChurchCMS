<?php

declare(strict_types=1);

$disclosures = is_array($disclosures ?? null) ? $disclosures : [];
?>
<section class="content-section">
    <header class="content-heading">
        <p class="eyebrow">Образование</p>
        <h1>Обязательные сведения</h1>
        <p>Опубликованные сведения образовательной организации.</p>
    </header>

    <?php if ($disclosures === []): ?>
        <p>Опубликованных разделов пока нет.</p>
    <?php else: ?>
        <div class="content-list">
            <?php foreach ($disclosures as $disclosure): ?>
                <article class="content-card">
                    <h2><a href="<?= $theme->e($disclosure['url']) ?>"><?= $theme->e($disclosure['title']) ?></a></h2>
                    <?php if ($disclosure['summary'] !== ''): ?><p><?= $theme->e($disclosure['summary']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
