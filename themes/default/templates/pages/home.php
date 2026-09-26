<?php declare(strict_types=1); ?>
<section class="hero">
    <p class="eyebrow">ChurchCMS</p>
    <h1><?= $theme->e($heading ?? 'Современная CMS для приходов и духовных школ') ?></h1>
    <p><?= $theme->e($lead ?? 'Система находится в разработке.') ?></p>
</section>

<section class="cards">
    <?= $theme->component('component.card', [
        'title' => 'Независимое ядро',
        'text' => 'PHP 8.3+, без обязательных внешних runtime-зависимостей.',
    ]) ?>
    <?= $theme->component('component.card', [
        'title' => 'Сменный дизайн',
        'text' => 'Контент и бизнес-логика не зависят от конкретной темы оформления.',
    ]) ?>
</section>
