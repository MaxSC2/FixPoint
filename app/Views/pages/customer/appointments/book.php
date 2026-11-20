<?php
declare(strict_types=1);

$request = isset($request) && is_array($request) ? $request : [];
$technician = isset($technician) && is_array($technician) ? $technician : null;
$meetingTypes = isset($meeting_types) && is_array($meeting_types) ? $meeting_types : [];
$days = isset($days) && is_array($days) ? array_values(array_filter($days, 'is_array')) : [];
$slots = isset($slots) && is_array($slots) ? array_values(array_filter($slots, 'is_array')) : [];
$appointments = isset($appointments) && is_array($appointments)
    ? array_values(array_filter($appointments, 'is_array'))
    : [];
$blockedReason = isset($blocked_reason) && is_string($blocked_reason) ? $blocked_reason : null;
$canBook = !empty($can_book) && $blockedReason === null;
$selectedDate = isset($date) ? (string) $date : '';
$timezone = isset($timezone) ? (string) $timezone : 'UTC';
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];
$requestId = (int) ($requestId ?? $request['id'] ?? 0);
$selectedMeetingType = (string) ($old['meeting_type'] ?? 'consultation');
$comment = (string) ($old['customer_comment'] ?? '');
$selectedStart = (string) ($old['start_utc'] ?? '');
$publicNumber = (string) ($request['public_number'] ?? 'Заявка');
$deviceTitle = trim(implode(' ', array_filter([
    (string) ($request['device_brand'] ?? ''),
    (string) ($request['device_model'] ?? ''),
])));
if ($deviceTitle === '') {
    $deviceTitle = (string) ($request['device_type'] ?? 'Устройство');
}
$pageTitle = 'Запись на встречу — ' . $publicNumber;
$activeNav = 'requests';
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<link rel="stylesheet" href="/assets/css/calendar.css">
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div>
                <nav class="breadcrumbs" aria-label="Хлебные крошки">
                    <a href="/requests">Мои заявки</a><span aria-hidden="true">/</span>
                    <a href="/requests/<?= e((string) $requestId) ?>"><?= e($publicNumber) ?></a><span aria-hidden="true">/</span>
                    <span aria-current="page">Запись на встречу</span>
                </nav>
                <span class="eyebrow">Сервисный центр / Запись</span>
                <h1>Выберите время встречи</h1>
                <p><?= e($deviceTitle) ?> · <?= e($publicNumber) ?>. Свободные слоты показаны в часовом поясе <?= e($timezone) ?>.</p>
            </div>
            <div class="staff-heading-note">
                <span>Мастер</span>
                <strong><?= e((string) ($technician['full_name'] ?? 'Не назначен')) ?></strong>
                <small>перенос и отмена — в карточке встречи</small>
            </div>
        </div>

        <div class="appointment-booking-layout">
            <section class="calendar-panel appointment-booking-main" data-reveal>
                <?php if ($blockedReason !== null): ?>
                    <div class="alert alert--warning" role="status">
                        <span class="alert__marker" aria-hidden="true"></span>
                        <p><?= e($blockedReason) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div>
                <?php endif; ?>

                <?php if ($canBook): ?>
                    <form class="appointment-booking-form" method="post" action="/requests/<?= e((string) $requestId) ?>/appointments/book">
                        <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                        <input type="hidden" name="date" value="<?= e($selectedDate) ?>">

                        <fieldset class="appointment-fieldset">
                            <legend>Цель встречи</legend>
                            <div class="appointment-choice-grid">
                                <?php foreach ($meetingTypes as $value => $label): ?>
                                    <?php $choiceId = 'meeting-type-' . (string) $value; ?>
                                    <label class="appointment-choice<?= $selectedMeetingType === (string) $value ? ' is-selected' : '' ?>" for="<?= e($choiceId) ?>">
                                        <input id="<?= e($choiceId) ?>" type="radio" name="meeting_type" value="<?= e((string) $value) ?>"<?= $selectedMeetingType === (string) $value ? ' checked' : '' ?>>
                                        <span class="appointment-choice__mark" aria-hidden="true"></span>
                                        <span class="appointment-choice__label"><?= e((string) $label) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($errors['meeting_type'])): ?><small class="field__error"><?= e((string) $errors['meeting_type']) ?></small><?php endif; ?>
                        </fieldset>

                        <div class="appointment-date-strip" role="group" aria-label="Дата встречи">
                            <?php foreach ($days as $day): ?>
                                <?php $dayDate = (string) ($day['date'] ?? ''); ?>
                                <a class="appointment-date<?= !empty($day['is_selected']) ? ' is-selected' : '' ?><?= (int) ($day['slots_count'] ?? 0) === 0 ? ' is-full' : '' ?><?= !empty($day['is_today']) ? ' is-today' : '' ?>"
                                   href="/requests/<?= e((string) $requestId) ?>/appointments/book?date=<?= e(rawurlencode($dayDate)) ?>"
                                   aria-label="<?= e((string) ($day['weekday_short'] ?? '')) ?>, <?= e((string) ($day['day'] ?? '')) ?> <?= e((string) ($day['month_label'] ?? '')) ?>, свободно <?= e((string) ($day['slots_count'] ?? 0)) ?>">
                                    <span><?= e((string) ($day['weekday_short'] ?? '')) ?></span>
                                    <strong><?= e((string) ($day['day'] ?? '')) ?></strong>
                                    <small><?= e((string) ($day['month_label'] ?? '')) ?></small>
                                    <em><?= (int) ($day['slots_count'] ?? 0) > 0 ? e((string) $day['slots_count']) . ' сл.' : 'нет' ?></em>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <fieldset class="appointment-fieldset">
                            <legend>Свободное время</legend>
                            <?php if ($slots === []): ?>
                                <p class="appointment-empty">На эту дату свободных слотов нет. Выберите другой день.</p>
                            <?php else: ?>
                                <div class="appointment-slot-grid">
                                    <?php foreach ($slots as $slot): ?>
                                        <?php $slotValue = (string) ($slot['start_utc'] ?? ''); $slotId = 'slot-' . md5($slotValue); ?>
                                        <label class="appointment-slot<?= $selectedStart === $slotValue ? ' is-selected' : '' ?>" for="<?= e($slotId) ?>">
                                            <input id="<?= e($slotId) ?>" type="radio" name="start_utc" value="<?= e($slotValue) ?>"<?= $selectedStart === $slotValue ? ' checked' : '' ?>>
                                            <span><?= e((string) ($slot['label'] ?? '')) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($errors['start_utc'])): ?><small class="field__error"><?= e((string) $errors['start_utc']) ?></small><?php endif; ?>
                        </fieldset>

                        <label class="field<?= isset($errors['customer_comment']) ? ' field--invalid' : '' ?>">
                            <span class="field__label">Комментарий <span class="field__optional">необязательно</span></span>
                            <textarea name="customer_comment" rows="3" maxlength="2000" placeholder="Что нужно подготовить или уточнить?"><?= e($comment) ?></textarea>
                            <small class="field__help">Комментарий увидит назначенный мастер. Не указывайте лишние персональные данные.</small>
                            <?php if (isset($errors['customer_comment'])): ?><small class="field__error"><?= e((string) $errors['customer_comment']) ?></small><?php endif; ?>
                        </label>

                        <div class="calendar-form-actions">
                            <button class="button button--primary" type="submit">Забронировать время</button>
                            <a class="button button--ghost" href="/requests/<?= e((string) $requestId) ?>">Вернуться к заявке</a>
                        </div>
                        <p class="appointment-hold-note">Выбранное время удерживается за вами до подтверждения мастером, но не дольше ограниченного срока. Если не подтвердить встречу, слот освободится автоматически.</p>
                    </form>
                <?php else: ?>
                    <p class="appointment-empty">Запись недоступна. Вернитесь к заявке или свяжитесь с сервисом.</p>
                    <a class="button button--secondary" href="/requests/<?= e((string) $requestId) ?>">К заявке</a>
                <?php endif; ?>
            </section>

            <aside class="calendar-aside appointment-booking-aside">
                <section class="calendar-summary-card" data-reveal>
                    <span class="eyebrow">Ваша заявка</span>
                    <h2><?= e($publicNumber) ?></h2>
                    <dl class="calendar-summary-list">
                        <div><dt>Устройство</dt><dd><?= e($deviceTitle) ?></dd></div>
                        <div><dt>Мастер</dt><dd><?= e((string) ($technician['full_name'] ?? 'Не назначен')) ?></dd></div>
                        <div><dt>Часовой пояс</dt><dd><?= e($timezone) ?></dd></div>
                        <div><dt>Длительность</dt><dd><?= e((string) ($settings['slot_minutes'] ?? 60)) ?> мин</dd></div>
                    </dl>
                </section>

                <section class="calendar-summary-card" data-reveal>
                    <span class="eyebrow">История встреч</span>
                    <h2>По этой заявке</h2>
                    <?php if ($appointments === []): ?>
                        <p class="calendar-aside__empty">Встреч пока нет.</p>
                    <?php else: ?>
                        <div class="calendar-upcoming-list">
                            <?php foreach ($appointments as $appointment): ?>
                                <a class="calendar-upcoming" href="/appointments/<?= e((string) (int) ($appointment['id'] ?? 0)) ?>">
                                    <span class="calendar-upcoming__date"><?= e((string) ($appointment['local_date'] ?? '')) ?><small><?= e((string) ($appointment['local_time'] ?? '')) ?></small></span>
                                    <span class="calendar-upcoming__body">
                                        <strong><?= e((string) ($appointment['meeting_type_label'] ?? 'Встреча')) ?></strong>
                                        <small><?= e((string) ($appointment['status_label'] ?? '')) ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
