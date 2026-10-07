<?php declare(strict_types=1);
$eventLabels = [
    'publication.published' => 'Публикация опубликована',
    'publication.withdrawn' => 'Публикация снята',
];
$statusLabels = [
    'pending' => 'Ожидает',
    'retry' => 'Повтор',
    'delivered' => 'Доставлено',
    'dead' => 'Остановлено',
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Внешнее распространение</p>
            <h1>Исходящие webhooks</h1>
            <p>Подписанные уведомления о публикации и снятии материалов. Сбой внешнего endpoint не блокирует сайт.</p>
        </div>
    </header>

    <?php if (!empty($webhookStatus)): ?>
        <?= $theme->component('admin.state', $webhookStatus) ?>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <section class="editor-card">
            <h2>Новый endpoint</h2>
            <form method="post" action="<?= $theme->e($theme->route('admin_distribution_webhooks_create')) ?>">
                <?= $theme->csrfInput() ?>
                <div class="editor-grid">
                    <label class="field">
                        <span>Название</span>
                        <input name="name" maxlength="160" required placeholder="Например: Епархиальный агрегатор">
                    </label>
                    <label class="field">
                        <span>HTTPS endpoint</span>
                        <input name="endpoint_url" type="url" maxlength="2048" required placeholder="https://partner.example/webhooks/churchcms">
                    </label>
                </div>
                <label class="field">
                    <span>Secret</span>
                    <input name="secret" type="password" minlength="24" maxlength="512" required autocomplete="new-password">
                    <small>Ключ шифруется в БД и больше не показывается в интерфейсе.</small>
                </label>
                <fieldset class="field">
                    <legend>События</legend>
                    <?php foreach ($eventLabels as $value => $label): ?>
                        <label><input type="checkbox" name="event_types[]" value="<?= $theme->e($value) ?>" checked> <?= $theme->e($label) ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <button class="button button--primary" type="submit">Создать webhook</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="editor-card">
        <h2>Endpoint'ы</h2>
        <?php if (empty($endpoints)): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Webhook endpoint’ов пока нет',
                'message' => 'Добавьте HTTPS endpoint, если внешняя система должна получать события публикаций.',
            ]) ?>
        <?php else: ?>
            <div class="editorial-list">
                <?php foreach ($endpoints as $endpoint): ?>
                    <article class="editorial-item">
                        <div class="editorial-item__main">
                            <div class="editorial-item__meta">
                                <span class="status-pill"><?= $endpoint->active ? 'Включён' : 'Отключён' ?></span>
                                <?php foreach ($endpoint->eventTypes as $eventType): ?>
                                    <span><?= $theme->e($eventLabels[$eventType] ?? $eventType) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <h3><?= $theme->e($endpoint->name) ?></h3>
                            <p><?= $theme->e($endpoint->endpointUrl) ?></p>
                        </div>
                        <?php if (!empty($canManage)): ?>
                            <form method="post" action="<?= $theme->e($theme->route($endpoint->active ? 'admin_distribution_webhooks_disable' : 'admin_distribution_webhooks_enable', ['publicId' => $endpoint->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <button class="button button--quiet" type="submit"><?= $endpoint->active ? 'Отключить' : 'Включить' ?></button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="editor-card">
        <h2>Последние доставки</h2>
        <?php if (empty($deliveries)): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Доставок пока нет',
                'message' => 'Они появятся после публикации или снятия материала.',
            ]) ?>
        <?php else: ?>
            <div class="editorial-list">
                <?php foreach ($deliveries as $delivery): ?>
                    <article class="editorial-item">
                        <div class="editorial-item__main">
                            <div class="editorial-item__meta">
                                <span class="status-pill"><?= $theme->e($statusLabels[$delivery['status']] ?? (string) $delivery['status']) ?></span>
                                <span><?= $theme->e($eventLabels[$delivery['event_type']] ?? (string) $delivery['event_type']) ?></span>
                                <span><?= (int) $delivery['attempt_count'] ?> попыток</span>
                            </div>
                            <h3><?= $theme->e((string) $delivery['endpoint_name']) ?></h3>
                            <p>
                                <?= $theme->e((string) $delivery['created_at']) ?> UTC
                                <?php if ($delivery['response_status'] !== null): ?> · HTTP <?= (int) $delivery['response_status'] ?><?php endif; ?>
                                <?php if (!empty($delivery['last_error_code'])): ?> · <?= $theme->e((string) $delivery['last_error_code']) ?><?php endif; ?>
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
