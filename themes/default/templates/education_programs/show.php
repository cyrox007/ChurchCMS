<?php

declare(strict_types=1);

$program = is_array($program ?? null) ? $program : [];
?>
<article class="content-section">
    <header class="content-heading">
        <p class="eyebrow">Образовательная программа</p>
        <h1><?= $theme->e((string) ($program['title'] ?? '')) ?></h1>
        <p><?= $theme->e((string) ($program['education_level'] ?? '')) ?> · <?= $theme->e((string) ($program['study_form'] ?? '')) ?></p>
    </header>

    <dl class="content-meta">
        <?php if (($program['duration_months'] ?? null) !== null): ?><div><dt>Длительность</dt><dd><?= (int) $program['duration_months'] ?> мес.</dd></div><?php endif; ?>
        <?php if (($program['qualification'] ?? null) !== null): ?><div><dt>Квалификация</dt><dd><?= $theme->e((string) $program['qualification']) ?></dd></div><?php endif; ?>
    </dl>

    <?php if (($program['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $program['summary']) ?></p><?php endif; ?>
    <?php if (($program['admission_note'] ?? '') !== ''): ?>
        <section><h2>Условия поступления</h2><p><?= nl2br($theme->e((string) $program['admission_note'])) ?></p></section>
    <?php endif; ?>
    <?php if (($program['description_html'] ?? '') !== ''): ?>
        <section class="rich-text"><?= (string) $program['description_html'] ?></section>
    <?php endif; ?>
</article>
