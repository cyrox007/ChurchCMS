<?php

declare(strict_types=1);

$search = is_array($search ?? null)
    ? $search
    : ['query' => '', 'items' => []];
$error = is_string($searchError ?? null)
    ? $searchError
    : null;
$query = (string) ($search['query'] ?? '');
$items = is_array($search['items'] ?? null)
    ? $search['items']
    : [];
?>
<section class="content-section">
    <header class="section-heading">
        <p class="eyebrow">Поиск</p>
        <h1>Поиск по сайту</h1>
        <p>Публикации, страницы, документы, люди и события.</p>
    </header>

    <form method="get" action="<?= $theme->e($theme->route('search_index')) ?>">
        <label>
            <span class="sr-only">Поисковый запрос</span>
            <input
                type="search"
                name="q"
                value="<?= $theme->e($query) ?>"
                minlength="2"
                maxlength="120"
                placeholder="Что найти?"
            >
        </label>
        <button class="button button--primary" type="submit">
            Найти
        </button>
    </form>

    <?php if ($error !== null): ?>
        <p role="alert"><?= $theme->e($error) ?></p>
    <?php elseif ($query !== '' && $items === []): ?>
        <p>Ничего не найдено.</p>
    <?php elseif ($items !== []): ?>
        <div class="card-grid">
            <?php foreach ($items as $item): ?>
                <article class="card">
                    <p class="eyebrow">
                        <?= $theme->e((string) ($item['type'] ?? '')) ?>
                    </p>
                    <h2>
                        <a href="<?= $theme->e((string) ($item['url'] ?? '#')) ?>">
                            <?= $theme->e((string) ($item['title'] ?? '')) ?>
                        </a>
                    </h2>
                    <?php if (!empty($item['excerpt'])): ?>
                        <p><?= $theme->e((string) $item['excerpt']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
