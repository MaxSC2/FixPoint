<?php
declare(strict_types=1);

$settings = isset($settings) && is_array($settings) ? $settings : [];
$timezones = isset($timezones) && is_array($timezones) ? $timezones : ['UTC' => 'UTC'];
$weekdays = isset($weekdays) && is_array($weekdays) ? $weekdays : [];
$weekdayShort = isset($weekdayShort) && is_array($weekdayShort) ? $weekdayShort : [];
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$currentTimezone = (string) ($old['timezone'] ?? $settings['timezone'] ?? 'UTC');
$slotMinutes = (int) ($old['slot_minutes'] ?? $settings['slot_minutes'] ?? 60);
$minNotice = (int) ($old['min_notice_minutes'] ?? $settings['min_notice_minutes'] ?? 120);
$pendingHold = (int) ($old['pending_hold_minutes'] ?? $settings['pending_hold_minutes'] ?? 1440);
$cancellationCutoff = (int) ($old['cancellation_cutoff_minutes'] ?? $settings['cancellation_cutoff_minutes'] ?? 120);
$savedIntervals = isset($settings['intervals']) && is_array($settings['intervals']) ? $settings['intervals'] : [];
$oldIntervals = isset($old['intervals']) && is_array($old['intervals']) ? $old['intervals'] : null;
$rowsFor = static function (int $weekday) use ($oldIntervals, $savedIntervals): array {
    $source = $oldIntervals[$weekday] ?? $oldIntervals[(string) $weekday] ?? null;
    if (is_array($source)) {
        $source = isset($source['start']) || isset($source['end']) ? [$source] : array_values($source);
        return array_values(array_filter($source, 'is_array'));
    }
    $source = $savedIntervals[$weekday] ?? [];
    return is_array($source) ? array_values(array_filter($source, 'is_array')) : [];
};
$generalError = (string) ($errors['general'] ?? '');
$pageTitle = 'Рабочее расписание';
$activeNav = 'technician';
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<link rel="stylesheet" href="/assets/css/calendar.css">
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div>
                <span class="eyebrow">Календарь / Настройки мастера</span>
                <h1>Рабочее расписание</h1>
                <p>Задайте дни, интервалы и правила записи. Время хранится в UTC, а клиенты и календарь видят его в выбранном часовом поясе.</p>
                <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/technician/calendar">Календарь</a><span aria-hidden="true">/</span><span aria-current="page">Расписание</span></nav>
            </div>
            <div class="staff-heading-note"><span>Пояс</span><strong class="calendar-heading-zone"><?= e($currentTimezone) ?></strong><small>слот <?= e((string) $slotMinutes) ?> мин</small></div>
        </div>

        <nav class="workspace-nav" aria-label="Разделы мастера" data-reveal>
            <a class="workspace-nav__link" href="/technician/requests">Очередь</a>
            <a class="workspace-nav__link" href="/technician/calendar">Календарь</a>
            <a class="workspace-nav__link" href="/technician/calendar/blocks">Блокировки</a>
            <a class="workspace-nav__link is-active" href="/technician/calendar/settings" aria-current="page">Расписание</a>
            <a class="workspace-nav__link" href="/technician/profile">Мой профиль</a>
        </nav>

        <?php if ($generalError !== ''): ?>
            <div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e($generalError) ?></p></div>
        <?php endif; ?>
        <?php if (isset($errors['intervals'])): ?>
            <div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['intervals']) ?></p></div>
        <?php endif; ?>

        <form class="calendar-settings-form" method="post" action="/technician/calendar/settings">
            <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">

            <section class="calendar-panel calendar-settings-panel" data-reveal>
                <div class="calendar-panel__head">
                    <div><span class="eyebrow">Правила записи</span><h2>Параметры слотов</h2></div>
                    <span class="calendar-panel__hint">Меняются для новых записей</span>
                </div>
                <div class="calendar-settings-fields">
                    <label class="field<?= isset($errors['timezone']) ? ' field--invalid' : '' ?>">
                        <span class="field__label">Часовой пояс</span>
                        <select name="timezone">
                            <?php foreach ($timezones as $value => $label): ?>
                                <option value="<?= e((string) $value) ?>"<?= $currentTimezone === (string) $value ? ' selected' : '' ?>><?= e((string) $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['timezone'])): ?><small class="field__error"><?= e((string) $errors['timezone']) ?></small><?php endif; ?>
                    </label>

                    <label class="field<?= isset($errors['slot_minutes']) ? ' field--invalid' : '' ?>">
                        <span class="field__label">Длительность встречи</span>
                        <select name="slot_minutes">
                            <?php foreach ([30, 60, 90] as $minutes): ?>
                                <option value="<?= e((string) $minutes) ?>"<?= $slotMinutes === $minutes ? ' selected' : '' ?>><?= e((string) $minutes) ?> минут</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['slot_minutes'])): ?><small class="field__error"><?= e((string) $errors['slot_minutes']) ?></small><?php endif; ?>
                    </label>

                    <label class="field<?= isset($errors['min_notice_minutes']) ? ' field--invalid' : '' ?>">
                        <span class="field__label">Минимальный запас до записи</span>
                        <input type="number" name="min_notice_minutes" min="0" max="20160" step="15" value="<?= e((string) $minNotice) ?>">
                        <small class="field__help">В минутах. 120 — запись не позже чем за 2 часа.</small>
                        <?php if (isset($errors['min_notice_minutes'])): ?><small class="field__error"><?= e((string) $errors['min_notice_minutes']) ?></small><?php endif; ?>
                    </label>

                    <label class="field<?= isset($errors['pending_hold_minutes']) ? ' field--invalid' : '' ?>">
                        <span class="field__label">Удержание неподтверждённого слота</span>
                        <input type="number" name="pending_hold_minutes" min="60" max="43200" step="60" value="<?= e((string) $pendingHold) ?>">
                        <small class="field__help">В минутах. По умолчанию 1440 — 24 часа.</small>
                        <?php if (isset($errors['pending_hold_minutes'])): ?><small class="field__error"><?= e((string) $errors['pending_hold_minutes']) ?></small><?php endif; ?>
                    </label>

                    <label class="field<?= isset($errors['cancellation_cutoff_minutes']) ? ' field--invalid' : '' ?>">
                        <span class="field__label">Срок отмены клиентом</span>
                        <input type="number" name="cancellation_cutoff_minutes" min="0" max="10080" step="15" value="<?= e((string) $cancellationCutoff) ?>">
                        <small class="field__help">За сколько минут до встречи клиент ещё может отменить подтверждённую запись.</small>
                        <?php if (isset($errors['cancellation_cutoff_minutes'])): ?><small class="field__error"><?= e((string) $errors['cancellation_cutoff_minutes']) ?></small><?php endif; ?>
                    </label>
                </div>
            </section>

            <section class="calendar-panel calendar-settings-panel" data-reveal>
                <div class="calendar-panel__head">
                    <div><span class="eyebrow">Неделя</span><h2>Рабочие интервалы</h2></div>
                    <span class="calendar-panel__hint">До трёх интервалов в день</span>
                </div>
                <p class="calendar-settings-note">Пустой день считается выходным. Интервалы одного дня не должны пересекаться. Обеденный перерыв задаётся двумя интервалами, например 09:00–13:00 и 14:00–18:00.</p>
                <div class="calendar-week-editor">
                    <?php foreach ($weekdays as $weekday => $label): ?>
                        <?php $rows = $rowsFor((int) $weekday); ?>
                        <div class="calendar-week-editor__day">
                            <div class="calendar-week-editor__name">
                                <strong><?= e((string) $label) ?></strong>
                                <span><?= e((string) ($weekdayShort[$weekday] ?? '')) ?></span>
                            </div>
                            <div class="calendar-week-editor__rows">
                                <?php for ($index = 0; $index < 3; $index++): ?>
                                    <?php $row = $rows[$index] ?? []; ?>
                                    <div class="calendar-week-editor__row">
                                        <label class="sr-only" for="interval-<?= e((string) $weekday) ?>-<?= e((string) $index) ?>-start">Начало <?= e((string) $label) ?>, интервал <?= e((string) ($index + 1)) ?></label>
                                        <input id="interval-<?= e((string) $weekday) ?>-<?= e((string) $index) ?>-start" type="time" name="intervals[<?= e((string) $weekday) ?>][<?= e((string) $index) ?>][start]" value="<?= e((string) ($row['start'] ?? '')) ?>">
                                        <span aria-hidden="true">—</span>
                                        <label class="sr-only" for="interval-<?= e((string) $weekday) ?>-<?= e((string) $index) ?>-end">Окончание <?= e((string) $label) ?>, интервал <?= e((string) ($index + 1)) ?></label>
                                        <input id="interval-<?= e((string) $weekday) ?>-<?= e((string) $index) ?>-end" type="time" name="intervals[<?= e((string) $weekday) ?>][<?= e((string) $index) ?>][end]" value="<?= e((string) ($row['end'] ?? '')) ?>">
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="calendar-form-actions">
                    <button class="button button--primary" type="submit">Сохранить расписание</button>
                    <a class="button button--ghost" href="/technician/calendar">Отмена</a>
                </div>
            </section>
        </form>

        <p class="workspace-footnote">Изменение расписания не переписывает уже согласованные встречи. Если новый график делает подтверждённую встречу невозможной, перенесите её вручную.</p>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
