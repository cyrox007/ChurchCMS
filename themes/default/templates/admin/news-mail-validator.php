<?php declare(strict_types=1);
$issues = is_array($issues ?? null) ? $issues : [];
$configured = ($configured ?? false) === true;
$technicalReady = ($technicalReady ?? false) === true;
$partnerApproved = ($partnerApproved ?? false) === true;
$contractConfirmed = ($contractConfirmed ?? false) === true;
$diagnosticCode = is_string($diagnosticCode ?? null) ? $diagnosticCode : null;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Внешнее распространение</p>
            <h1>Проверка контура Новости Mail</h1>
            <p>ChurchCMS проверяет технический полнотекстовый RSS для согласования, но не выдаёт его за официальный протокол Mail до подтверждения партнёром.</p>
        </div>
        <div class="admin-heading__actions">
            <a class="button button--quiet" href="<?= $theme->e($theme->route('feed_news_mail')) ?>" target="_blank" rel="noopener">Открыть фид</a>
            <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_syndication_exports')) ?>">К журналу</a>
        </div>
    </header>

    <?php if (!$configured): ?>
        <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Новости Mail пока не включён', 'message' => 'Настройте канонический site_url и включите syndication.targets.news_mail.enabled.']) ?>
    <?php elseif (!$technicalReady): ?>
        <?= $theme->component('admin.state', ['kind' => 'error', 'title' => 'Технический фид ещё не готов', 'message' => 'Исправьте ошибки ниже и добавьте хотя бы один материал.']) ?>
    <?php elseif ($diagnosticCode === 'partner_access_required'): ?>
        <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Технически готово — требуется ответ партнёра', 'message' => 'Код: partner_access_required. Подайте заявку представителя СМИ и подтвердите с Mail конкретный способ передачи материалов.']) ?>
    <?php else: ?>
        <?= $theme->component('admin.state', ['kind' => 'success', 'title' => 'Контур Новости Mail подтверждён', 'message' => 'Технический фид исправен, заявка одобрена и способ доставки подтверждён партнёром.']) ?>
    <?php endif; ?>

    <section class="admin-operations-section" aria-labelledby="news-mail-summary">
        <div class="admin-operations-heading"><div><p class="card__eyebrow">Состояние</p><h2 id="news-mail-summary">Готовность интеграции</h2></div></div>
        <div class="admin-operations-list" role="list">
            <article class="admin-operations-row" role="listitem"><div class="admin-operations-row__body">
                <strong>Материалов: <?= (int) ($itemCount ?? 0) ?></strong>
                <span>Ошибок: <?= (int) ($errorCount ?? 0) ?> · предупреждений: <?= (int) ($warningCount ?? 0) ?></span>
                <small>Заявка: <?= $partnerApproved ? 'одобрена' : 'ожидается' ?> · протокол доставки: <?= $contractConfirmed ? 'подтверждён' : 'не подтверждён' ?></small>
            </div></article>
        </div>
    </section>

    <?php if ($issues !== []): ?>
        <section class="admin-operations-section" aria-labelledby="news-mail-issues">
            <div class="admin-operations-heading"><div><p class="card__eyebrow">Диагностика</p><h2 id="news-mail-issues">Замечания фида</h2></div></div>
            <div class="admin-operations-list" role="list">
                <?php foreach ($issues as $issue): ?>
                    <article class="admin-operations-row" role="listitem"><div class="admin-operations-row__body">
                        <strong><?= ($issue['level'] ?? '') === 'error' ? 'Ошибка' : 'Предупреждение' ?></strong>
                        <span><?= $theme->e((string) ($issue['message'] ?? '')) ?></span>
                        <small>код: <?= $theme->e((string) ($issue['code'] ?? 'unknown')) ?><?php if (($issue['item'] ?? null) !== null): ?> · материал №<?= (int) $issue['item'] ?><?php endif; ?></small>
                    </div></article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
