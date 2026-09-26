<?php declare(strict_types=1); ?>
<section class="hero">
    <div class="hero__content">
        <p class="eyebrow"><?= $theme->e($eyebrow ?? 'ChurchCMS') ?></p>
        <h1><?= $theme->e($heading ?? 'Современная CMS для приходов и духовных школ') ?></h1>
        <p class="hero__lead">
            <?= $theme->e($lead ?? 'Самостоятельное PHP 8.3+ ядро, модульная архитектура и сменные темы для разных типов церковных и образовательных сайтов.') ?>
        </p>

        <div class="hero__actions">
            <a class="button button--primary" href="#capabilities">Возможности</a>
            <a class="button button--quiet" href="<?= $theme->e($theme->route('health')) ?>">Проверить runtime</a>
        </div>
    </div>

    <aside class="hero__panel" aria-label="Принципы базовой темы">
        <p class="hero__panel-kicker">Наследие → современный интерфейс</p>
        <p class="hero__panel-title">Синий архитектурный мотив и бордовый акцент сохранены из legacy-дизайна.</p>
        <dl class="hero__stats">
            <div>
                <dt>PHP</dt>
                <dd>8.3+</dd>
            </div>
            <div>
                <dt>Runtime</dt>
                <dd>0 deps</dd>
            </div>
            <div>
                <dt>Theme</dt>
                <dd>replaceable</dd>
            </div>
        </dl>
    </aside>
</section>

<section class="section" id="capabilities" aria-labelledby="capabilities-title">
    <div class="section-heading">
        <p class="eyebrow">Основа</p>
        <h2 id="capabilities-title">Один движок — разные сайты и дизайн</h2>
        <p>Базовая тема задаёт безопасный и доступный фундамент. Приход, кафедральный собор или семинария могут наследовать её и менять только нужные представления.</p>
    </div>

    <div class="feature-grid">
        <?= $theme->component('component.card', [
            'eyebrow' => '01',
            'title' => 'Контент отдельно от дизайна',
            'text' => 'Модули передают данные по логическому контракту, а тема сама решает, как их показывать.',
        ]) ?>

        <?= $theme->component('component.card', [
            'eyebrow' => '02',
            'title' => 'Адаптивность по умолчанию',
            'text' => 'Никакой фиксированной ширины старого сайта: сетки, типографика и отступы рассчитаны на телефон, планшет и десктоп.',
        ]) ?>

        <?= $theme->component('component.card', [
            'eyebrow' => '03',
            'title' => 'Доступный интерфейс',
            'text' => 'Высокий контраст, заметный focus, skip-link, semantic HTML и поддержка reduced motion входят в базовый контракт.',
        ]) ?>
    </div>
</section>

<section class="section section--tinted">
    <div class="section-heading section-heading--compact">
        <p class="eyebrow">Визуальное направление</p>
        <h2>Узнаваемость без визуального наследия 2000-х</h2>
    </div>

    <div class="principles">
        <article>
            <h3>Глубокий синий</h3>
            <p>Сохраняет академическую и архитектурную ассоциацию старого оформления, но используется крупными спокойными поверхностями.</p>
        </article>
        <article>
            <h3>Бордовый акцент</h3>
            <p>Наследует цвет ссылок legacy-сайта и теперь применяется точечно: активные элементы, кнопки и смысловые маркеры.</p>
        </article>
        <article>
            <h3>Светлая типографика</h3>
            <p>Тёплый фон, крупный набор и ограниченная длина строк делают длинные исторические и образовательные материалы удобнее для чтения.</p>
        </article>
    </div>
</section>
