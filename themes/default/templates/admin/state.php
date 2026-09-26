<?php

declare(strict_types=1);

$kind = (string) ($props['kind'] ?? 'empty');
if (!in_array($kind, ['empty', 'success', 'error', 'loading'], true)) {
    $kind = 'empty';
}

$title = trim((string) ($props['title'] ?? ''));
$message = trim((string) ($props['message'] ?? ''));
$actionHref = trim((string) ($props['action_href'] ?? ''));
$actionLabel = trim((string) ($props['action_label'] ?? ''));

$role = match ($kind) {
    'error' => 'alert',
    'success', 'loading' => 'status',
    default => null,
};
$live = $kind === 'error' ? 'assertive' : ($role !== null ? 'polite' : null);
?>
<section
    class="admin-state admin-state--<?= $theme->e($kind) ?>"
    <?php if ($role !== null): ?>role="<?= $theme->e($role) ?>"<?php endif; ?>
    <?php if ($live !== null): ?>aria-live="<?= $theme->e($live) ?>"<?php endif; ?>
    <?php if ($kind === 'loading'): ?>aria-busy="true"<?php endif; ?>
>
    <div class="admin-state__mark" aria-hidden="true">
        <?= match ($kind) {
            'success' => '✓',
            'error' => '!',
            'loading' => '…',
            default => '○',
        } ?>
    </div>

    <div class="admin-state__body">
        <?php if ($title !== ''): ?>
            <h2><?= $theme->e($title) ?></h2>
        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <p><?= $theme->e($message) ?></p>
        <?php endif; ?>

        <?php if ($actionHref !== '' && $actionLabel !== ''): ?>
            <p class="admin-state__action">
                <a class="button button--primary" href="<?= $theme->e($actionHref) ?>">
                    <?= $theme->e($actionLabel) ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>
