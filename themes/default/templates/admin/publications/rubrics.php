<?php

declare(strict_types=1);

$rubrics = is_array($rubrics ?? null) ? $rubrics : [];
$canManage = ($canManage ?? false) === true;
$status = (string) ($status ?? '');
$statusMessages = [
    'created' => ['success', 'Рубрика создана', 'Теперь её можно назначать публикациям.'],
    'updated' => ['success', 'Рубрика сохранена', 'Название, порядок и вложенность обновлены.'],
    'deleted' => ['success', 'Рубрика удалена', 'Свободная рубрика удалена из рубрикатора.'],
    'invalid' => ['error', 'Не удалось сохранить рубрику', 'Проверьте название, родительскую рубрику и глубину вложенности.'],
    'in-use' => ['error', 'Рубрика используется', 'Сначала снимите её с публикаций, затем повторите удаление.'],
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Контент</p>
            <h1>Рубрики публикаций</h1>
            <p>Иерархическая классификация новостей и материалов. Рубрики не заменяют разделы сайта и теги.</p>
        </div>
        <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_publications')) ?>">← К публикациям</a>
    </header>

    <?php if (isset($statusMessages[$status])): ?>
        <?php [$kind, $stateTitle, $message] = $statusMessages[$status]; ?>
        <?= $theme->component('admin.state', [
            'kind' => $kind,
            'title' => $stateTitle,
            'message' => $message,
        ]) ?>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <div>
                <p class="eyebrow">Новая рубрика</p>
                <h2>Добавить рубрику</h2>
                <p>Для вложенной рубрики выберите родительскую. Например: «Епархиальные отделы → Молодёжный отдел».</p>
            </div>

            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_publication_rubric_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label>
                    <span>Название</span>
                    <input name="name" maxlength="120" required placeholder="Например: Богослужения">
                </label>
                <label>
                    <span>Родительская рубрика</span>
                    <select name="parent_public_id">
                        <option value="">Верхний уровень</option>
                        <?php foreach ($rubrics as $rubric): ?>
                            <option value="<?= $theme->e((string) $rubric['public_id']) ?>">
                                <?= $theme->e(str_repeat('— ', (int) $rubric['depth']) . (string) $rubric['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Порядок</span>
                    <input type="number" name="sort_order" value="0" min="-9999" max="9999">
                </label>
                <button class="button button--primary" type="submit">Добавить рубрику</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <div>
            <p class="eyebrow">Структура</p>
            <h2>Дерево рубрик</h2>
            <p>Рубрики можно вкладывать друг в друга. Количество материалов показано рядом с каждой рубрикой.</p>
        </div>

        <?php if ($rubrics === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Рубрик пока нет',
                'message' => 'Создайте верхнюю рубрику, а затем при необходимости добавьте вложенные.',
            ]) ?>
        <?php else: ?>
            <ul class="rubric-tree">
                <?php foreach ($rubrics as $rubric): ?>
                    <?php
                    $rubricPublicId = (string) $rubric['public_id'];
                    $depth = min(3, max(0, (int) $rubric['depth']));
                    $usageCount = (int) $rubric['usage_count'];
                    ?>
                    <li class="rubric-tree__item" data-depth="<?= $depth ?>">
                        <div class="rubric-tree__main">
                            <strong><?= $theme->e((string) $rubric['name']) ?></strong>
                            <small>
                                /<?= $theme->e((string) $rubric['slug']) ?>
                                · публикаций: <?= $usageCount ?>
                                <?php if (!empty($rubric['parent_name'])): ?>
                                    · внутри «<?= $theme->e((string) $rubric['parent_name']) ?>»
                                <?php endif; ?>
                            </small>
                        </div>

                        <?php if ($canManage): ?>
                            <details class="admin-inline-editor">
                                <summary>Изменить</summary>
                                <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_publication_rubric_update', ['publicId' => $rubricPublicId])) ?>">
                                    <?= $theme->csrfInput() ?>
                                    <label>
                                        <span>Название</span>
                                        <input name="name" maxlength="120" required value="<?= $theme->e((string) $rubric['name']) ?>">
                                    </label>
                                    <label>
                                        <span>Родительская рубрика</span>
                                        <select name="parent_public_id">
                                            <option value="">Верхний уровень</option>
                                            <?php foreach ($rubrics as $candidate): ?>
                                                <?php if ((string) $candidate['public_id'] === $rubricPublicId) { continue; } ?>
                                                <option
                                                    value="<?= $theme->e((string) $candidate['public_id']) ?>"
                                                    <?= (string) ($rubric['parent_public_id'] ?? '') === (string) $candidate['public_id'] ? 'selected' : '' ?>
                                                ><?= $theme->e(str_repeat('— ', (int) $candidate['depth']) . (string) $candidate['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Порядок</span>
                                        <input type="number" name="sort_order" min="-9999" max="9999" value="<?= (int) $rubric['sort_order'] ?>">
                                    </label>
                                    <button class="button button--primary" type="submit">Сохранить рубрику</button>
                                </form>

                                <form method="post" action="<?= $theme->e($theme->route('admin_publication_rubric_delete', ['publicId' => $rubricPublicId])) ?>">
                                    <?= $theme->csrfInput() ?>
                                    <button class="button button--quiet" type="submit" <?= $usageCount > 0 ? 'disabled' : '' ?>>Удалить рубрику</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</section>
