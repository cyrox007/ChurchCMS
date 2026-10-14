<?php

declare(strict_types=1);

$latestPublications = is_array($latestPublications ?? null)
    ? array_values($latestPublications)
    : [];
$homeNavigation = is_array($navigation ?? null)
    ? array_values($navigation)
    : [];

$typeLabels = [
    'news' => 'Новости',
    'article' => 'Статья',
    'announcement' => 'Объявление',
];

$publicationMeta = static function (array $publication) use ($typeLabels): array {
    $type = trim((string) ($publication['type'] ?? ''));
    $publishedAt = trim((string) ($publication['published_at'] ?? ''));
    $date = '';

    if ($publishedAt !== '') {
        try {
            $date = (new DateTimeImmutable($publishedAt))->format('d.m.Y');
        } catch (Throwable) {
            $date = '';
        }
    }

    $source = is_array($publication['source'] ?? null)
        ? $publication['source']
        : [];
    $sourceName = trim((string) ($source['name'] ?? ''));
    $isFederation = ($source['kind'] ?? null) === 'federation';

    return [
        'type' => $typeLabels[$type] ?? 'Материал',
        'date' => $date,
        'source' => $isFederation && $sourceName !== ''
            ? $sourceName
            : '',
    ];
};
?>
<section class="home-intro" aria-labelledby="home-title">
    <div class="home-intro__content">
        <p class="eyebrow">Официальный сайт</p>
        <h1 id="home-title">
            <?= $theme->e($heading ?? 'ChurchCMS') ?>
        </h1>
        <p class="home-intro__lead">
            <?= $theme->e(
                $lead
                ?? 'Новости, события, богослужения и материалы организации.'
            ) ?>
        </p>
    </div>

    <aside class="home-intro__note" aria-label="Навигация по актуальным материалам">
        <strong>Жизнь организации</strong>
        <span>
            Последние события, объявления и публикации собраны на главной странице.
        </span>
    </aside>
</section>

<?php if ($latestPublications !== []): ?>
    <?php
    $featured = array_shift($latestPublications);
    $featuredMeta = $publicationMeta($featured);
    $featuredUrl = trim((string) ($featured['url'] ?? ''));
    ?>
    <section class="home-section" aria-labelledby="latest-publications-title">
        <header class="home-section__heading">
            <div>
                <p class="eyebrow">Актуальное</p>
                <h2 id="latest-publications-title">Последние новости и материалы</h2>
            </div>
            <a href="/publications">Все публикации →</a>
        </header>

        <div class="news-feed">
            <article class="news-feature">
                <p class="news-feature__meta">
                    <span class="news-feature__type">
                        <?= $theme->e($featuredMeta['type']) ?>
                    </span>
                    <?php if ($featuredMeta['date'] !== ''): ?>
                        <span><?= $theme->e($featuredMeta['date']) ?></span>
                    <?php endif; ?>
                    <?php if ($featuredMeta['source'] !== ''): ?>
                        <span> · <?= $theme->e($featuredMeta['source']) ?></span>
                    <?php endif; ?>
                </p>

                <h3>
                    <?php if ($featuredUrl !== ''): ?>
                        <a href="<?= $theme->e($featuredUrl) ?>">
                            <?= $theme->e((string) ($featured['title'] ?? '')) ?>
                        </a>
                    <?php else: ?>
                        <?= $theme->e((string) ($featured['title'] ?? '')) ?>
                    <?php endif; ?>
                </h3>

                <?php if (!empty($featured['excerpt'])): ?>
                    <p class="news-feature__excerpt">
                        <?= $theme->e((string) $featured['excerpt']) ?>
                    </p>
                <?php endif; ?>
            </article>

            <?php foreach ($latestPublications as $publication): ?>
                <?php
                $meta = $publicationMeta($publication);
                $url = trim((string) ($publication['url'] ?? ''));
                ?>
                <article class="news-row">
                    <p class="news-row__meta">
                        <span class="news-row__type">
                            <?= $theme->e($meta['type']) ?>
                        </span>
                        <?php if ($meta['date'] !== ''): ?>
                            <span><?= $theme->e($meta['date']) ?></span>
                        <?php endif; ?>
                        <?php if ($meta['source'] !== ''): ?>
                            <span><?= $theme->e($meta['source']) ?></span>
                        <?php endif; ?>
                    </p>

                    <div>
                        <h3>
                            <?php if ($url !== ''): ?>
                                <a href="<?= $theme->e($url) ?>">
                                    <?= $theme->e((string) ($publication['title'] ?? '')) ?>
                                </a>
                            <?php else: ?>
                                <?= $theme->e((string) ($publication['title'] ?? '')) ?>
                            <?php endif; ?>
                        </h3>

                        <?php if (!empty($publication['excerpt'])): ?>
                            <p class="news-row__excerpt">
                                <?= $theme->e((string) $publication['excerpt']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if ($url !== ''): ?>
                        <a class="news-row__more" href="<?= $theme->e($url) ?>">
                            Подробнее
                        </a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <section class="home-section" aria-labelledby="latest-publications-title">
        <header class="home-section__heading">
            <div>
                <p class="eyebrow">Актуальное</p>
                <h2 id="latest-publications-title">Новости и материалы</h2>
            </div>
        </header>
        <div class="empty-state">
            <h3>Публикаций пока нет</h3>
            <p>Новые материалы появятся здесь после публикации редактором.</p>
        </div>
    </section>
<?php endif; ?>

<?php if ($homeNavigation !== []): ?>
    <section class="home-section" aria-labelledby="site-sections-title">
        <header class="home-section__heading">
            <div>
                <p class="eyebrow">Разделы</p>
                <h2 id="site-sections-title">Полезные разделы сайта</h2>
            </div>
        </header>

        <div class="home-quick-grid">
            <?php foreach (array_slice($homeNavigation, 0, 6) as $item): ?>
                <?php
                $label = is_array($item)
                    ? trim((string) ($item['label'] ?? ''))
                    : '';
                $url = is_array($item)
                    ? trim((string) ($item['url'] ?? ''))
                    : '';
                if ($label === '' || $url === '') {
                    continue;
                }
                ?>
                <a class="home-quick-link" href="<?= $theme->e($url) ?>">
                    <span><?= $theme->e($label) ?></span>
                    <span>Перейти к разделу →</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
