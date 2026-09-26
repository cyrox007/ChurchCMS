<?php

declare(strict_types=1);

$tasks = is_array($tasks ?? null) ? $tasks : [];
$count = max(0, (int) ($taskCount ?? 0));
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Задачи</p>
            <h1>Что требует внимания</h1>
            <p>ChurchCMS собирает редакционную работу, модерацию и ошибки интеграций в одном месте.</p>
        </div>
    </header>

    <?php if ($tasks === []): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'success',
            'title' => 'Срочных задач нет',
            'message' => 'Модерация, редакционная очередь и внешние каналы не требуют действий.',
        ]) ?>
    <?php else: ?>
        <div class="admin-task-list" aria-label="Текущие задачи">
            <?php foreach ($tasks as $task): ?>
                <?php
                $severity = (string) ($task['severity'] ?? 'info');
                $route = trim((string) ($task['route'] ?? ''));
                $params = is_array($task['route_params'] ?? null)
                    ? $task['route_params']
                    : [];
                $tag = $route !== '' ? 'a' : 'article';
                $href = $route !== ''
                    ? $theme->route($route, $params)
                    : '';
                ?>
                <<?= $tag ?>
                    class="admin-task admin-task--<?= $theme->e($severity) ?>"
                    <?php if ($href !== ''): ?>href="<?= $theme->e($href) ?>"<?php endif; ?>
                >
                    <span class="admin-task__count"><?= $theme->e((int) ($task['count'] ?? 0)) ?></span>
                    <span class="admin-task__body">
                        <strong><?= $theme->e((string) ($task['title'] ?? '')) ?></strong>
                        <?php if (!empty($task['description'])): ?>
                            <span><?= $theme->e((string) $task['description']) ?></span>
                        <?php endif; ?>
                    </span>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
