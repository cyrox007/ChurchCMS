<?php declare(strict_types=1);
$issues = is_array($issues ?? null) ? $issues : [];
$configured = ($configured ?? false) === true;
$technicalReady = ($technicalReady ?? false) === true;
$partnerApproved = ($partnerApproved ?? false) === true;
$diagnosticCode = is_string($diagnosticCode ?? null)
    ? $diagnosticCode
    : null;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Внешнее распространение</p>
            <h1>Проверка фида СМИ2</h1>
            <p>Проверяется полнотекстовый RSS 2.0 без обращения к внешним адресам и без недокументированных API СМИ2.</p>
        </div>
        <div class="admin-heading__actions">
            <a class="button button--quiet" href="<?= $theme->e($theme->route('feed_smi2')) ?>" target="_blank" rel="noopener">Открыть фид</a>
            <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_syndication_exports')) ?>">К журналу</a>
        </div>
    </header>

    <?php if (!$configured): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'empty',
            'title' => 'СМИ2 пока не включён',
            'message' => 'Настройте канонический syndication.site_url и включите syndication.targets.smi2.enabled в локальной конфигурации.',
        ]) ?>
    <?php elseif (!$technicalReady): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'error',
            'title' => 'Фид ещё не готов',
            'message' => 'Исправьте ошибки ниже. Для технической готовности нужен хотя бы один материал без ошибок.',
        ]) ?>
    <?php elseif ($diagnosticCode === 'partner_access_required'): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'empty',
            'title' => 'Технически готово — требуется подключение партнёра',
            'message' => 'Фид проходит внутреннюю проверку. Код: partner_access_required. Передайте /feeds/smi2.xml партнёрскому отделу СМИ2/«МирТесен» и после подтверждения выставьте partner_approved=true.',
        ]) ?>
    <?php else: ?>
        <?= $theme->component('admin.state', [
            'kind' => 'success',
            'title' => 'Контур СМИ2 готов',
            'message' => 'Полнотекстовый RSS проходит внутреннюю проверку, а партнёрское подключение отмечено как подтверждённое.',
        ]) ?>
    <?php endif; ?>

    <section class="admin-operations-section" aria-labelledby="smi2-summary-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Состояние</p>
                <h2 id="smi2-summary-title">Проверка готовности</h2>
            </div>
        </div>
        <div class="admin-operations-list" role="list">
            <article class="admin-operations-row" role="listitem">
                <div class="admin-operations-row__body">
                    <strong>Материалов: <?= (int) ($itemCount ?? 0) ?></strong>
                    <span>Ошибок: <?= (int) ($errorCount ?? 0) ?> · предупреждений: <?= (int) ($warningCount ?? 0) ?></span>
                    <small>Партнёрское подтверждение: <?= $partnerApproved ? 'получено' : 'ожидается' ?></small>
                </div>
            </article>
        </div>
    </section>

    <?php if ($issues !== []): ?>
        <section class="admin-operations-section" aria-labelledby="smi2-issues-title">
            <div class="admin-operations-heading">
                <div>
                    <p class="card__eyebrow">Диагностика</p>
                    <h2 id="smi2-issues-title">Замечания фида</h2>
                </div>
            </div>
            <div class="admin-operations-list" role="list">
                <?php foreach ($issues as $issue): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e(($issue['level'] ?? '') === 'error' ? 'Ошибка' : 'Предупреждение') ?></strong>
                            <span><?= $theme->e((string) ($issue['message'] ?? '')) ?></span>
                            <small>
                                код: <?= $theme->e((string) ($issue['code'] ?? 'unknown')) ?>
                                <?php if (($issue['item'] ?? null) !== null): ?>
                                    · материал №<?= (int) $issue['item'] ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
