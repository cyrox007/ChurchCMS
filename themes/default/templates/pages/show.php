<?php declare(strict_types=1); ?>
<article class="publication page-content" itemscope itemtype="https://schema.org/WebPage">
    <link
        itemprop="url"
        href="<?= $theme->e(\ChurchCMS\Core\SeoRenderer::absoluteUrl($canonicalPath)) ?>"
    >

    <?php if (!empty($breadcrumbs)): ?>
        <nav class="page-breadcrumbs" aria-label="Навигация по разделам">
            <ol>
                <?php foreach ($breadcrumbs as $item): ?>
                    <li>
                        <?php if (!empty($item['current'])): ?>
                            <span aria-current="page"><?= $theme->e($item['title']) ?></span>
                        <?php else: ?>
                            <a href="<?= $theme->e($item['path']) ?>"><?= $theme->e($item['title']) ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <header class="publication__header">
        <h1 itemprop="name"><?= $theme->e($page->title) ?></h1>
        <meta itemprop="dateModified" content="<?= $theme->e($page->updatedAt->format(DATE_ATOM)) ?>">
    </header>

    <div class="publication__body prose" itemprop="mainContentOfPage">
        <?= $page->bodyHtml ?>
    </div>
</article>
