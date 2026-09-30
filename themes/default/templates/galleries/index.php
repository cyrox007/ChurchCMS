<?php

declare(strict_types=1);

$galleries = is_array($galleries ?? null) ? $galleries : [];
?>
<section class="content-section">
    <header class="section-heading">
        <div>
            <p class="eyebrow">Медиатека</p>
            <h1><?= $theme->e($heading ?? 'Галереи') ?></h1>
        </div>
    </header>

    <?php if ($galleries === []): ?>
        <p>Опубликованных галерей пока нет.</p>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($galleries as $gallery): ?>
                <?php
                $cover = is_array($gallery['items'][0] ?? null)
                    ? $gallery['items'][0]
                    : null;
                $coverUrl = is_array($cover)
                    ? (string) ($cover['display_url'] ?? '')
                    : '';
                ?>
                <article class="card">
                    <?php if ($coverUrl !== ''): ?>
                        <a
                            href="<?= $theme->e((string) $gallery['url']) ?>"
                            aria-label="<?= $theme->e((string) $gallery['title']) ?>"
                        >
                            <img
                                src="<?= $theme->e($coverUrl) ?>"
                                alt="<?= $theme->e((string) ($cover['alt_text'] ?? $gallery['title'])) ?>"
                                loading="lazy"
                                decoding="async"
                            >
                        </a>
                    <?php endif; ?>

                    <div class="card__body">
                        <h2>
                            <a href="<?= $theme->e((string) $gallery['url']) ?>">
                                <?= $theme->e((string) $gallery['title']) ?>
                            </a>
                        </h2>

                        <?php if (trim((string) $gallery['description']) !== ''): ?>
                            <p><?= $theme->e((string) $gallery['description']) ?></p>
                        <?php endif; ?>

                        <small>
                            изображений: <?= $theme->e((int) $gallery['items_count']) ?>
                        </small>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
