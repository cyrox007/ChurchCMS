<?php

declare(strict_types=1);
?>
<section class="content-section">
    <header class="content-heading">
        <p class="eyebrow">Обязательные сведения</p>
        <h1><?= $theme->e($disclosure['title']) ?></h1>
        <?php if ($disclosure['summary'] !== ''): ?><p><?= $theme->e($disclosure['summary']) ?></p><?php endif; ?>
    </header>
    <article class="content-card">
        <?= $disclosure['body_html'] ?>
    </article>
</section>
