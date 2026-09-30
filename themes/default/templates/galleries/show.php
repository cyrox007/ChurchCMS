<?php

declare(strict_types=1);

$gallery = is_array($gallery ?? null) ? $gallery : [];
$items = is_array($gallery['items'] ?? null)
    ? $gallery['items']
    : [];
?>
<article class="content-section">
    <header class="section-heading">
        <div>
            <p class="eyebrow">Галерея</p>
            <h1><?= $theme->e((string) ($gallery['title'] ?? 'Галерея')) ?></h1>

            <?php if (trim((string) ($gallery['description'] ?? '')) !== ''): ?>
                <p><?= $theme->e((string) $gallery['description']) ?></p>
            <?php endif; ?>
        </div>
    </header>

    <div class="card-grid">
        <?php foreach ($items as $item): ?>
            <?php $imageUrl = (string) ($item['display_url'] ?? ''); ?>
            <?php if ($imageUrl === ''): ?>
                <?php continue; ?>
            <?php endif; ?>

            <figure class="card">
                <a
                    href="<?= $theme->e((string) ($item['original_url'] ?? $imageUrl)) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    <img
                        src="<?= $theme->e($imageUrl) ?>"
                        alt="<?= $theme->e((string) ($item['alt_text'] ?? $item['title'] ?? '')) ?>"
                        loading="lazy"
                        decoding="async"
                    >
                </a>

                <?php if (trim((string) ($item['title'] ?? '')) !== ''): ?>
                    <figcaption class="card__body">
                        <?= $theme->e((string) $item['title']) ?>
                    </figcaption>
                <?php endif; ?>
            </figure>
        <?php endforeach; ?>
    </div>
</article>
