<?php

declare(strict_types=1);

$query = trim((string) ($searchQuery ?? ''));
$valid = (bool) ($searchValid ?? false);
$groups = is_array($searchGroups ?? null) ? $searchGroups : [];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Поиск</p>
            <h1>Поиск по управлению</h1>
            <p>Ищите материалы и другие сущности, доступные вашей роли.</p>
        </div>
    </header>

    <?php if ($query === ''): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'empty',
            'title' => 'Введите запрос',
            'message' => 'Минимальная длина запроса — 2 символа.',
        ]) ?>
    <?php elseif (!$valid): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'error',
            'title' => 'Запрос слишком короткий',
            'message' => 'Введите не менее 2 символов.',
        ]) ?>
    <?php elseif ($groups === []): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'empty',
            'title' => 'Ничего не найдено',
            'message' => 'Попробуйте другое слово или часть названия.',
        ]) ?>
    <?php else: ?>
        <div class="admin-search-groups">
            <?php foreach ($groups as $group): ?>
                <?php
                $groupLabel = (string) ($group['label'] ?? '');
                $results = is_array($group['results'] ?? null)
                    ? $group['results']
                    : [];
                ?>
                <section class="admin-search-group">
                    <h2><?= $theme->e($groupLabel) ?></h2>

                    <div class="admin-search-results">
                        <?php foreach ($results as $result): ?>
                            <?php
                            $route = (string) ($result['route'] ?? '');
                            $params = is_array($result['route_params'] ?? null)
                                ? $result['route_params']
                                : [];
                            ?>
                            <a
                                class="admin-search-result"
                                href="<?= $theme->e($theme->route($route, $params)) ?>"
                            >
                                <strong><?= $theme->e((string) ($result['title'] ?? '')) ?></strong>
                                <?php if (!empty($result['description'])): ?>
                                    <span><?= $theme->e((string) $result['description']) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
