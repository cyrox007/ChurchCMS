<?php declare(strict_types=1); $items = is_array($items ?? null) ? $items : []; ?>
<section class="content-section">
    <header class="section-heading"><p class="eyebrow">Каталог</p><h1>Библиотека</h1></header>
    <?php if ($items === []): ?><p>Опубликованных изданий пока нет.</p><?php else: ?>
        <div class="content-grid">
            <?php foreach ($items as $item): ?>
                <article class="content-card">
                    <h2><a href="<?= $theme->e((string) ($item['url'] ?? '#')) ?>"><?= $theme->e((string) ($item['title'] ?? 'Издание')) ?></a></h2>
                    <?php if (($item['author_name'] ?? null) !== null): ?><p><?= $theme->e((string) $item['author_name']) ?></p><?php endif; ?>
                    <?php if (($item['publication_year'] ?? null) !== null): ?><p><strong>Год:</strong> <?= (int) $item['publication_year'] ?></p><?php endif; ?>
                    <?php if (($item['availability_note'] ?? null) !== null): ?><p><?= $theme->e((string) $item['availability_note']) ?></p><?php endif; ?>
                    <?php if (($item['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $item['summary']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
