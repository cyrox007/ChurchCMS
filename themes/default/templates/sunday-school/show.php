<?php declare(strict_types=1); $school = is_array($school ?? null) ? $school : []; ?>
<article class="content-section content-detail">
    <header class="section-heading">
        <p class="eyebrow">Воскресная школа</p>
        <h1><?= $theme->e((string) ($school['title'] ?? 'Воскресная школа')) ?></h1>
        <?php if (($school['summary'] ?? '') !== ''): ?><p><?= $theme->e((string) $school['summary']) ?></p><?php endif; ?>
    </header>
    <dl class="content-meta">
        <?php if (($school['leader_name'] ?? null) !== null): ?><div><dt>Руководитель</dt><dd><?= $theme->e((string) $school['leader_name']) ?></dd></div><?php endif; ?>
        <?php if (($school['location_name'] ?? null) !== null): ?><div><dt>Место занятий</dt><dd><?= $theme->e((string) $school['location_name']) ?></dd></div><?php endif; ?>
        <?php if (($school['age_info'] ?? null) !== null): ?><div><dt>Возраст</dt><dd><?= $theme->e((string) $school['age_info']) ?></dd></div><?php endif; ?>
        <?php if (($school['contact_email'] ?? null) !== null): ?><div><dt>Email</dt><dd><?= $theme->e((string) $school['contact_email']) ?></dd></div><?php endif; ?>
        <?php if (($school['contact_phone'] ?? null) !== null): ?><div><dt>Телефон</dt><dd><?= $theme->e((string) $school['contact_phone']) ?></dd></div><?php endif; ?>
    </dl>
    <?php if (($school['description_html'] ?? '') !== ''): ?><div class="article-body"><?= (string) $school['description_html'] ?></div><?php endif; ?>
</article>
