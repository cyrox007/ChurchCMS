<?php declare(strict_types=1); ?>
<section class="publication-hero">
    <p class="eyebrow">Архив</p>
    <h1><?= $theme->e($heading ?? 'Публикации') ?></h1>
    <p>Новости, статьи, объявления и другие опубликованные материалы.</p>
</section>

<?php if (empty($publications)): ?>
    <section class="empty-state" aria-live="polite">
        <h2>Пока нет опубликованных материалов</h2>
        <p>После публикации редактором материалы появятся здесь автоматически.</p>
    </section>
<?php else: ?>
    <section class="publication-list" aria-label="Список публикаций">
        <?php foreach ($publications as $publication): ?>
            <article class="publication-card">
                <p class="publication-card__meta">
                    <span><?= $theme->e($publication->type->value) ?></span>
                    <?php if ($publication->publishedAt !== null): ?>
                        <time datetime="<?= $theme->e($publication->publishedAt->format(DATE_ATOM)) ?>">
                            <?= $theme->e($publication->publishedAt->format('d.m.Y')) ?>
                        </time>
                    <?php endif; ?>
                </p>

                <h2>
                    <a href="<?= $theme->e($theme->route('publication_show', ['slug' => $publication->slug])) ?>">
                        <?= $theme->e($publication->title) ?>
                    </a>
                </h2>

                <?php
                $publicationTaxonomy = is_array($taxonomy[$publication->id] ?? null)
                    ? $taxonomy[$publication->id]
                    : ['categories' => [], 'tags' => []];
                ?>

                <?php if (!empty($publicationTaxonomy['categories'])): ?>
                    <div class="publication-taxonomy" aria-label="Категории">
                        <?php foreach ($publicationTaxonomy['categories'] as $category): ?>
                            <span class="publication-taxonomy__item publication-taxonomy__item--category">
                                <?= $theme->e((string) ($category['name'] ?? '')) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($publication->excerpt !== ''): ?>
                    <p><?= $theme->e($publication->excerpt) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>

    <?php
    $page = max(1, (int) ($page ?? 1));
    $perPage = max(1, (int) ($perPage ?? 12));
    $total = max(0, (int) ($total ?? 0));
    $hasPrevious = $page > 1;
    $hasNext = $page * $perPage < $total;
    ?>

    <?php if ($hasPrevious || $hasNext): ?>
        <nav class="pagination" aria-label="Навигация по страницам">
            <?php if ($hasPrevious): ?>
                <a href="<?= $theme->e($theme->route('publication_index', ['page' => $page - 1])) ?>">
                    ← Новее
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <span>Страница <?= $theme->e($page) ?></span>

            <?php if ($hasNext): ?>
                <a href="<?= $theme->e($theme->route('publication_index', ['page' => $page + 1])) ?>">
                    Раньше →
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
