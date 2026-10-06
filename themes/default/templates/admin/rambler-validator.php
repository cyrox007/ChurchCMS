<?php declare(strict_types=1);
$issues = is_array($issues ?? null) ? $issues : [];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Внешнее распространение</p>
            <h1>Проверка Rambler-фида</h1>
            <p>Проверка текущего фида по обязательным требованиям формата Rambler/новости.</p>
        </div>
        <div class="admin-heading__actions">
            <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_syndication_exports')) ?>">Журнал экспортов</a>
            <a class="button button--quiet" href="<?= $theme->e($theme->route('feed_rambler')) ?>" target="_blank" rel="noopener">Открыть фид</a>
        </div>
    </header>

    <?php if (!$configured): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'error',
            'title' => 'Rambler-фид не настроен',
            'message' => 'Укажите корректный syndication.site_url и включите target rambler в конфигурации.',
        ]) ?>
    <?php elseif ($errorCount === 0): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'success',
            'title' => 'Обязательные проверки пройдены',
            'message' => 'Проверено публикаций: ' . (int) $itemCount . '. Предупреждений: ' . (int) $warningCount . '.',
        ]) ?>
    <?php else: ?>
        <?= $theme->component('admin.state', [
            'kind' => 'error',
            'title' => 'Фид не готов к передаче',
            'message' => 'Ошибок: ' . (int) $errorCount . '. Предупреждений: ' . (int) $warningCount . '.',
        ]) ?>
    <?php endif; ?>

    <?php if ($configured && $issues !== []): ?>
        <div class="editorial-list">
            <?php foreach ($issues as $issue): ?>
                <?php
                $level = (string) ($issue['level'] ?? 'warning');
                $item = isset($issue['item']) && $issue['item'] !== null
                    ? (int) $issue['item']
                    : null;
                ?>
                <article class="editorial-item">
                    <div class="editorial-item__main">
                        <div class="editorial-item__meta">
                            <span class="status-pill"><?= $level === 'error' ? 'Ошибка' : 'Предупреждение' ?></span>
                            <span><?= $theme->e((string) ($issue['code'] ?? 'unknown')) ?></span>
                            <?php if ($item !== null): ?><span>Публикация №<?= $item ?></span><?php endif; ?>
                        </div>
                        <p><?= $theme->e((string) ($issue['message'] ?? '')) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($configured && $issues === []): ?>
        <p class="admin-help">Валидатор проверяет структуру XML и доступные локально требования. Доступность внешних URL и фактический размер изображений он не загружает и не проверяет.</p>
    <?php endif; ?>
</section>
