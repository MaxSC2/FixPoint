<?php
declare(strict_types=1);

/**
 * Presentational empty state.
 * Inputs: title, description, actionLabel (optional), actionHref (optional).
 */
$emptyTitle = isset($title) ? (string) $title : 'Пока ничего нет';
$emptyDescription = isset($description) ? (string) $description : '';
$emptyActionLabel = isset($actionLabel) ? (string) $actionLabel : '';
$emptyActionHref = isset($actionHref) ? (string) $actionHref : '';
?>
<div class="empty-state">
    <span class="empty-state__icon" aria-hidden="true">i</span>
    <h2><?= e($emptyTitle) ?></h2>
    <?php if ($emptyDescription !== ''): ?><p><?= e($emptyDescription) ?></p><?php endif; ?>
    <?php if ($emptyActionLabel !== '' && $emptyActionHref !== ''): ?>
        <a class="button button--secondary" href="<?= e($emptyActionHref) ?>"><?= e($emptyActionLabel) ?></a>
    <?php endif; ?>
</div>
