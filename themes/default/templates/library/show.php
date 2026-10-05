<?php declare(strict_types=1); $item = is_array($item ?? null) ? $item : []; ?>
<article class="content-section content-detail">
    <header class="section-heading">
        <p class="eyebrow">Библиотека</p>
        <h1><?= $theme->e((string) ($item['title'] ?? 'Издание')) ?></h1>
        <?php if (($item['author_name'] ?? null) !== null): ?><p><?= $theme->e((string) $item['author_name']) ?></p><?php endif; ?>
        <?php if (($item['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $item['summary']) ?></p><?php endif; ?>
    </header>
    <dl class="content-meta">
        <?php if (($item['publisher_name'] ?? null) !== null): ?><div><dt>Издательство</dt><dd><?= $theme->e((string) $item['publisher_name']) ?></dd></div><?php endif; ?>
        <?php if (($item['publication_year'] ?? null) !== null): ?><div><dt>Год</dt><dd><?= (int) $item['publication_year'] ?></dd></div><?php endif; ?>
        <?php if (($item['isbn'] ?? null) !== null): ?><div><dt>ISBN</dt><dd><?= $theme->e((string) $item['isbn']) ?></dd></div><?php endif; ?>
        <?php if (($item['shelf_code'] ?? null) !== null): ?><div><dt>Шифр/место хранения</dt><dd><?= $theme->e((string) $item['shelf_code']) ?></dd></div><?php endif; ?>
        <?php if (($item['availability_note'] ?? null) !== null): ?><div><dt>Доступность</dt><dd><?= $theme->e((string) $item['availability_note']) ?></dd></div><?php endif; ?>
    </dl>
    <?php if (($item['description_html'] ?? '') !== ''): ?><div class="article-body"><?= (string) $item['description_html'] ?></div><?php endif; ?>
</article>
