<?php declare(strict_types=1); ?>
<article class="publication">
    <header class="publication__header">
        <p class="eyebrow"><?= $theme->e($publication->type->value) ?></p>
        <h1><?= $theme->e($publication->title) ?></h1>

        <div class="publication__meta">
            <?php if ($publication->publishedAt !== null): ?>
                <time datetime="<?= $theme->e($publication->publishedAt->format(DATE_ATOM)) ?>">
                    <?= $theme->e($publication->publishedAt->format('d.m.Y')) ?>
                </time>
            <?php endif; ?>

            <?php if (!empty($publication->authorName)): ?>
                <span><?= $theme->e($publication->authorName) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($publication->excerpt !== ''): ?>
            <p class="publication__lead"><?= $theme->e($publication->excerpt) ?></p>
        <?php endif; ?>
    </header>

    <div class="publication__body prose">
        <?= $publication->bodyHtml ?>
    </div>
</article>

<nav class="article-back">
    <a href="<?= $theme->e($theme->route('publication_index')) ?>">← Все публикации</a>
</nav>
