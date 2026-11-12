<?php
declare(strict_types=1);

if (!isset($flash) || !is_array($flash) || !isset($flash['message'])) {
    return;
}

$flashType = (string) ($flash['type'] ?? 'info');
$allowedFlashTypes = ['success', 'error', 'info', 'warning'];
if (!in_array($flashType, $allowedFlashTypes, true)) {
    $flashType = 'info';
}
$flashRole = $flashType === 'error' ? 'alert' : 'status';
?>
<div class="container flash-container">
    <div class="alert alert--<?= e($flashType) ?>" role="<?= e($flashRole) ?>">
        <span class="alert__marker" aria-hidden="true"></span>
        <p><?= e((string) $flash['message']) ?></p>
    </div>
</div>
