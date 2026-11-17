<?php
declare(strict_types=1);

$status = isset($status) ? (string) $status : '';
$statusBadgeLabels = [
    'new' => ['label' => 'Новая', 'tone' => 'info'],
    'accepted' => ['label' => 'Принята', 'tone' => 'info'],
    'diagnostics' => ['label' => 'Диагностика', 'tone' => 'info'],
    'awaiting_customer_approval' => ['label' => 'Ожидает согласования', 'tone' => 'warning'],
    'awaiting_parts' => ['label' => 'Ожидает запчасти', 'tone' => 'warning'],
    'repairing' => ['label' => 'В ремонте', 'tone' => 'info'],
    'ready_for_pickup' => ['label' => 'Готова к выдаче', 'tone' => 'success'],
    'completed' => ['label' => 'Завершена', 'tone' => 'success'],
    'cancelled' => ['label' => 'Отменена', 'tone' => 'neutral'],
    'rejected' => ['label' => 'Отклонена', 'tone' => 'danger'],
];
$statusBadgeView = $statusBadgeLabels[$status] ?? ['label' => 'Статус уточняется', 'tone' => 'neutral'];
?>
<span class="status-badge status-badge--<?= e($statusBadgeView['tone']) ?>"><?= e($statusBadgeView['label']) ?></span>
