<?php
declare(strict_types=1);

$calendar = isset($calendar) && is_array($calendar) ? $calendar : [];
$settings = isset($calendar['settings']) && is_array($calendar['settings']) ? $calendar['settings'] : [];
$view = (string) ($calendar['view'] ?? 'month');
$date = (string) ($calendar['date'] ?? '');
$rangeLabel = (string) ($calendar['range_label'] ?? '');
$days = isset($calendar['days']) && is_array($calendar['days']) ? $calendar['days'] : [];
$weeks = isset($calendar['weeks']) && is_array($calendar['weeks']) ? $calendar['weeks'] : [];
$upcoming = isset($calendar['upcoming']) && is_array($calendar['upcoming']) ? array_values(array_filter($calendar['upcoming'], 'is_array')) : [];
$timezone = (string) ($calendar['timezone'] ?? 'UTC');
$pageTitle = 'Календарь мастера';
$activeNav = 'technician';
$viewUrl = static function (string $nextView, string $nextDate) use ($date): string {
    return '/technician/calendar?' . http_build_query(['view' => $nextView, 'date' => $nextDate === '' ? $date : $nextDate]);
};
$weekdayLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
$compact = $view === 'month';
if ($view === 'month') {
    $dayList = [];
    foreach ($weeks as $week) {
        if (!is_array($week)) {
            continue;
        }
        foreach ($week as $day) {
            if (is_array($day)) {
                $dayList[] = $day;
            }
        }
    }
} else {
    $dayList = array_values(array_filter($days, 'is_array'));
}
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<link rel="stylesheet" href="/assets/css/calendar.css">
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div>
                <span class="eyebrow">Рабочее пространство / Мастер</span>
                <h1>Календарь встреч</h1>
                <p>Рабочие часы, личные блокировки и заявки клиентов. Время показано в часовом поясе <?= e($timezone) ?>.</p>
            </div>
            <div class="staff-heading-note">
                <span>Ожидают</span>
                <strong><?= e((string) ($calendar['pending_count'] ?? 0)) ?></strong>
                <small>подтверждено: <?= e((string) ($calendar['confirmed_count'] ?? 0)) ?></small>
            </div>
        </div>

        <nav class="workspace-nav" aria-label="Разделы мастера" data-reveal>
            <a class="workspace-nav__link" href="/technician/requests">Очередь</a>
            <a class="workspace-nav__link is-active" href="/technician/calendar" aria-current="page">Календарь</a>
            <a class="workspace-nav__link" href="/technician/calendar/blocks">Блокировки</a>
            <a class="workspace-nav__link" href="/technician/calendar/settings">Расписание</a>
            <a class="workspace-nav__link" href="/technician/profile">Мой профиль</a>
        </nav>

        <section class="calendar-toolbar" data-reveal>
            <div class="calendar-toolbar__nav">
                <a class="button button--secondary button--small" href="<?= e($viewUrl($view, (string) ($calendar['prev_date'] ?? $date))) ?>" aria-label="Предыдущий период">←</a>
                <a class="button button--secondary button--small" href="<?= e($viewUrl($view, (string) ($calendar['today_date'] ?? $date))) ?>">Сегодня</a>
                <a class="button button--secondary button--small" href="<?= e($viewUrl($view, (string) ($calendar['next_date'] ?? $date))) ?>" aria-label="Следующий период">→</a>
            </div>
            <div class="calendar-toolbar__range">
                <span class="eyebrow">Период</span>
                <strong><?= e($rangeLabel) ?></strong>
            </div>
            <div class="calendar-toolbar__views" role="group" aria-label="Вид календаря">
                <?php foreach (['day' => 'День', 'week' => 'Неделя', 'month' => 'Месяц'] as $value => $label): ?>
                    <a class="calendar-view-switch<?= $view === $value ? ' is-active' : '' ?>" href="<?= e($viewUrl($value, $date)) ?>"<?= $view === $value ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="calendar-layout">
            <section class="calendar-panel" data-reveal>
                <div class="calendar-legend" aria-label="Обозначения календаря">
                    <span class="calendar-legend__item"><span class="calendar-legend__marker calendar-legend__marker--working" aria-hidden="true"></span>Рабочие часы</span>
                    <span class="calendar-legend__item"><span class="calendar-legend__marker calendar-legend__marker--personal" aria-hidden="true"></span>Личная блокировка</span>
                    <span class="calendar-legend__item"><span class="calendar-legend__marker calendar-legend__marker--vacation" aria-hidden="true"></span>Отпуск / выходной</span>
                    <span class="calendar-legend__item"><span class="calendar-legend__marker calendar-legend__marker--pending" aria-hidden="true"></span>Ожидает</span>
                    <span class="calendar-legend__item"><span class="calendar-legend__marker calendar-legend__marker--confirmed" aria-hidden="true"></span>Подтверждена</span>
                    <span class="calendar-legend__item"><span class="calendar-legend__marker calendar-legend__marker--completed" aria-hidden="true"></span>Завершена</span>
                    <span class="calendar-legend__item"><span class="calendar-legend__marker calendar-legend__marker--cancelled" aria-hidden="true"></span>Отменена</span>
                </div>

                <?php if ($view === 'month'): ?>
                    <div class="calendar-weekdays" aria-hidden="true">
                        <?php foreach ($weekdayLabels as $label): ?><span><?= e($label) ?></span><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="calendar-grid calendar-grid--<?= e($view) ?>">
                    <?php foreach ($dayList as $day): ?>
                        <?php require dirname(__DIR__, 3) . '/components/calendar-day.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="calendar-aside">
                <section class="calendar-summary-card" data-reveal>
                    <span class="eyebrow">Настройки</span>
                    <h2>Рабочий ритм</h2>
                    <dl class="calendar-summary-list">
                        <div><dt>Часовой пояс</dt><dd><?= e($timezone) ?></dd></div>
                        <div><dt>Длительность встречи</dt><dd><?= e((string) ($settings['slot_minutes'] ?? 60)) ?> мин</dd></div>
                        <div><dt>Запись не позже чем за</dt><dd><?= e((string) ($settings['min_notice_minutes'] ?? 0)) ?> мин</dd></div>
                        <div><dt>Удержание слота</dt><dd><?= e((string) round(((int) ($settings['pending_hold_minutes'] ?? 1440)) / 60)) ?> ч</dd></div>
                    </dl>
                    <div class="calendar-summary-card__actions">
                        <a class="button button--secondary button--small" href="/technician/calendar/settings">Изменить расписание</a>
                        <a class="button button--ghost button--small" href="/technician/calendar/blocks">Блокировки</a>
                    </div>
                </section>

                <section class="calendar-summary-card" data-reveal>
                    <span class="eyebrow">Ближайшие встречи</span>
                    <h2>Что впереди</h2>
                    <?php if ($upcoming === []): ?>
                        <p class="calendar-aside__empty">Активных встреч нет. Новые заявки появятся после записи клиентов.</p>
                    <?php else: ?>
                        <div class="calendar-upcoming-list">
                            <?php foreach (array_slice($upcoming, 0, 8) as $appointment): ?>
                                <?php $tone = (string) ($appointment['status_tone'] ?? 'neutral'); ?>
                                <a class="calendar-upcoming" href="/technician/appointments/<?= e((string) (int) ($appointment['id'] ?? 0)) ?>">
                                    <span class="calendar-upcoming__date"><?= e((string) ($appointment['local_date'] ?? '')) ?><small><?= e((string) ($appointment['local_time'] ?? '')) ?></small></span>
                                    <span class="calendar-upcoming__body">
                                        <strong><?= e((string) ($appointment['meeting_type_label'] ?? 'Встреча')) ?></strong>
                                        <small><?= e((string) ($appointment['customer_name'] ?? '')) ?> · <?= e((string) ($appointment['request_number'] ?? '')) ?></small>
                                    </span>
                                    <span class="calendar-status calendar-status--<?= e($tone) ?>"><?= e((string) ($appointment['status_label'] ?? '')) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </aside>
        </div>

        <p class="workspace-footnote">Клиенты видят только свободные и занятые интервалы. Названия и заметки личных блокировок остаются внутренними. Незавершённая заявка удерживает слот ограниченное время, затем освобождается автоматически.</p>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
