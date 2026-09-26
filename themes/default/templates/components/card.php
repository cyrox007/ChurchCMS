<?php declare(strict_types=1); ?>
<article class="card">
    <?php if (!empty($props['title'])): ?>
        <h2><?= $theme->e($props['title']) ?></h2>
    <?php endif; ?>

    <?php if (!empty($props['text'])): ?>
        <p><?= $theme->e($props['text']) ?></p>
    <?php endif; ?>

    <?php if (!empty($slots['default'])): ?>
        <div class="card__slot"><?= $slots['default'] ?></div>
    <?php endif; ?>
</article>
