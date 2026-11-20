<?php
declare(strict_types=1);

/**
 * One calendar day cell, shared by technician month/week/day views.
 *
 * Expected: $day (array), $compact (bool). Month cells show the first few
 * events; week/day cells show every event with customer context.
 */
$day = isset($day) && is_array($day) ? $day : [];
$compact = !empty($compact);
$events = isset($day['events']) && is_array($day['events']) ? array_values(array_filter($day['events'], 'is_array')) : [];
$visibleEvents = $compact ? array_slice($events, 0, 4) : $events;
$hiddenCount = max(0, count($events) - count($visibleEvents));
$allowedMarkers = ['pending', 'confirmed', 'completed', 'cancelled', 'declined', 'no_show', 'reschedule_proposed', 'vacation', 'holiday', 'personal', 'other'];
$dayClasses = ['calendar-day'];
if (empty($day['is_current_month'])) {
    $dayClasses[] = 'calendar-day--outside';
}
if (empty($day['is_working'])) {
    $dayClasses[] = 'calendar-day--off';
}
if (!empty($day['is_today'])) {
    $dayClasses[] = 'calendar-day--today';
}
$intervals = isset($day['intervals']) && is_array($day['intervals']) ? $day['intervals'] : [];
?>
<article class="<?= e(implode(' ', $dayClasses)) ?>" data-calendar-date="<?= e((string) ($day['date'] ?? '')) ?>">
    <header class="calendar-day__head">
        <div>
            <span class="calendar-day__weekday"><?= e((string) ($day['weekday_short'] ?? '')) ?></span>
            <strong class="calendar-day__number"><?= e((string) ($day['day'] ?? '')) ?></strong>
            <span class="calendar-day__month"><?= e((string) ($day['month_label'] ?? '')) ?></span>
        </div>
        <?php if ($compact): ?>
            <span class="calendar-day__count" title="Активные встречи">
                <?= e((string) ((int) ($day['pending_count'] ?? 0) + (int) ($day['confirmed_count'] ?? 0))) ?>
            </span>
        <?php endif; ?>
    </header>

    <?php if ($intervals !== []): ?>
        <div class="calendar-day__hours">
            <?php foreach ($intervals as $interval): ?>
                <span><?= e((string) ($interval['start'] ?? '')) ?>–<?= e((string) ($interval['end'] ?? '')) ?></span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="calendar-day__hours calendar-day__hours--off">Выходной</div>
    <?php endif; ?>

    <div class="calendar-day__events">
        <?php foreach ($visibleEvents as $event): ?>
            <?php
            $marker = (string) ($event['marker'] ?? 'other');
            $marker = in_array($marker, $allowedMarkers, true) ? $marker : 'other';
            $type = (string) ($event['type'] ?? 'block');
            $href = $event['href'] ?? null;
            $eventClasses = 'calendar-event calendar-event--' . $type . ' calendar-event--' . $marker;
            if (!empty($event['is_proposal'])) {
                $eventClasses .= ' calendar-event--proposal';
            }
            if (!empty($event['is_expired'])) {
                $eventClasses .= ' calendar-event--expired';
            }
            ?>
            <?php if (is_string($href) && $href !== ''): ?>
                <a class="<?= e($eventClasses) ?>" href="<?= e($href) ?>">
            <?php else: ?>
                <div class="<?= e($eventClasses) ?>">
            <?php endif; ?>
                    <span class="calendar-event__marker" aria-hidden="true"></span>
                    <span class="calendar-event__body">
                        <span class="calendar-event__meta">
                            <span class="calendar-event__time"><?= e((string) ($event['time_label'] ?? '')) ?></span>
                            <span class="calendar-event__status"><?= e((string) ($event['status_label'] ?? $event['title'] ?? '')) ?></span>
                        </span>
                        <span class="calendar-event__title"><?= e((string) ($event['title'] ?? '')) ?></span>
                        <?php if (!$compact && (string) ($event['customer_name'] ?? '') !== ''): ?>
                            <span class="calendar-event__subtitle"><?= e((string) $event['customer_name']) ?> · <?= e((string) ($event['subtitle'] ?? '')) ?></span>
                        <?php elseif ($compact && (string) ($event['subtitle'] ?? '') !== ''): ?>
                            <span class="calendar-event__subtitle"><?= e((string) $event['subtitle']) ?></span>
                        <?php endif; ?>
                    </span>
            <?php if (is_string($href) && $href !== ''): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($hiddenCount > 0): ?>
            <span class="calendar-day__more">+<?= e((string) $hiddenCount) ?> ещё</span>
        <?php endif; ?>
        <?php if ($visibleEvents === []): ?>
            <span class="calendar-day__empty">Свободно</span>
        <?php endif; ?>
    </div>
</article>
