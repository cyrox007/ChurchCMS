<?php declare(strict_types=1);
$canonical = ChurchCMSCoreSeoRenderer::absoluteUrl('/publications/' . rawurlencode($publication->slug));
$shareLinks = ChurchCMSCoreConfig::get('sharing.enabled', true) === true
    ? $theme->shareLinks($canonical, $publication->title)
    : [];
?>
<article
    class="publication"
    itemscope
    itemtype="https://schema.org/<?= $publication->type->value === 'news' ? 'NewsArticle' : 'Article' ?>"
>
    <meta itemprop="headline" content="<?= $theme->e($publication->title) ?>">
    <link itemprop="mainEntityOfPage" href="<?= $theme->e($canonical) ?>">
    <?php if ($publication->publishedAt !== null): ?>
        <meta itemprop="datePublished" content="<?= $theme->e($publication->publishedAt->format(DATE_ATOM)) ?>">
    <?php endif; ?>
    <meta itemprop="dateModified" content="<?= $theme->e($publication->updatedAt->format(DATE_ATOM)) ?>">
    <?php if (!empty($seo['image'])): ?>
        <link itemprop="image" href="<?= $theme->e(\ChurchCMS\Core\SeoRenderer::absoluteUrl((string) $seo['image'])) ?>">
    <?php endif; ?>

    <header class="publication__header">
        <p class="eyebrow"><?= $theme->e($publication->type->value) ?></p>
        <h1 itemprop="headline"><?= $theme->e($publication->title) ?></h1>

        <div class="publication__meta">
            <?php if ($publication->publishedAt !== null): ?>
                <time
                    itemprop="datePublished"
                    datetime="<?= $theme->e($publication->publishedAt->format(DATE_ATOM)) ?>"
                >
                    <?= $theme->e($publication->publishedAt->format('d.m.Y')) ?>
                </time>
            <?php endif; ?>

            <?php if (!empty($publication->authorName)): ?>
                <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                    <span itemprop="name"><?= $theme->e($publication->authorName) ?></span>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($publication->excerpt !== ''): ?>
            <p class="publication__lead" itemprop="description"><?= $theme->e($publication->excerpt) ?></p>
        <?php endif; ?>
    </header>

    <div class="publication__body prose" itemprop="articleBody">
        <?= $publication->bodyHtml ?>
    </div>
</article>

<?php if ($shareLinks !== []): ?>
    <section class="share-panel" aria-label="Поделиться публикацией">
        <span class="share-panel__label">Поделиться</span>
        <div class="share-panel__actions">
            <button
                class="share-button share-button--native"
                type="button"
                data-share-native
                data-share-url="<?= $theme->e($canonical) ?>"
                data-share-title="<?= $theme->e($publication->title) ?>"
            >
                Поделиться
            </button>

            <?php foreach ($shareLinks as $share): ?>
                <a
                    class="share-button share-button--<?= $theme->e($share['provider']) ?>"
                    href="<?= $theme->e($share['url']) ?>"
                    target="_blank"
                    rel="nofollow noopener noreferrer"
                >
                    <?= $theme->e($share['label']) ?>
                </a>
            <?php endforeach; ?>

            <button
                class="share-button"
                type="button"
                data-copy-link
                data-share-url="<?= $theme->e($canonical) ?>"
            >
                Скопировать ссылку
            </button>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($commentsAvailable)): ?>
    <?= $theme->partial('comments.section', [
        'publication' => $publication,
        'comments' => $comments ?? [],
        'commentFlash' => $commentFlash ?? null,
        'commentsMaxLength' => $commentsMaxLength ?? 4000,
    ]) ?>
<?php endif; ?>

<nav class="article-back">
    <a href="<?= $theme->e($theme->route('publication_index')) ?>">← Все публикации</a>
</nav>
