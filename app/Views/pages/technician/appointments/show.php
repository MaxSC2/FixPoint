<?php
declare(strict_types=1);

$appointment = isset($appointment) && is_array($appointment) ? $appointment : [];
$events = isset($events) && is_array($events) ? array_values(array_filter($events, 'is_array')) : [];
$permissions = isset($permissions) && is_array($permissions) ? $permissions : [];
$rescheduleSlots = isset($reschedule_slots) && is_array($reschedule_slots)
    ? array_values(array_filter($reschedule_slots, 'is_array'))
    : [];
$rescheduleDate = isset($reschedule_date) ? (string) $reschedule_date : '';
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];
$appointmentId = (int) ($appointmentId ?? $appointment['id'] ?? 0);
$statusTone = (string) ($appointment['status_tone'] ?? 'neutral');
$selectedStart = (string) ($old['start_utc'] ?? '');
$pageTitle = 'Встреча — ' . (string) ($appointment['request_number'] ?? '');
$activeNav = 'technician';
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<link rel="stylesheet" href="/assets/css/calendar.css">
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--detail" data-reveal>
            <div>
                <nav class="breadcrumbs" aria-label="Хлебные крошки">
                    <a href="/technician/requests">Очередь</a><span aria-hidden="true">/</span>
                    <a href="/technician/calendar">Календарь</a><span aria-hidden="true">/</span>
                    <span aria-current="page"><?= e((string) ($appointment['request_number'] ?? 'Встреча')) ?></span>
                </nav>
                <span class="eyebrow">Календарь / <?= e((string) ($appointment['meeting_type_label'] ?? '')) ?></span>
                <h1><?= e((string) ($appointment['local_date'] ?? '')) ?>, <?= e((string) ($appointment['local_time'] ?? '')) ?></h1>
                <p><?= e((string) ($appointment['device_title'] ?? '')) ?> · <?= e((string) ($appointment['customer_name'] ?? '')) ?> · <?= e((string) ($appointment['request_number'] ?? '')) ?></p>
            </div>
            <div class="request-detail-status">
                <span class="request-detail-status__label">Статус встречи</span>
                <span class="calendar-status calendar-status--<?= e($statusTone) ?> calendar-status--large"><?= e((string) ($appointment['status_label'] ?? '')) ?></span>
                <small><?= e((string) ($appointment['timezone'] ?? 'UTC')) ?></small>
            </div>
        </div>

        <nav class="workspace-nav" aria-label="Разделы мастера" data-reveal>
            <a class="workspace-nav__link" href="/technician/requests">Очередь</a>
            <a class="workspace-nav__link is-active" href="/technician/calendar" aria-current="page">Календарь</a>
            <a class="workspace-nav__link" href="/technician/calendar/blocks">Блокировки</a>
            <a class="workspace-nav__link" href="/technician/calendar/settings">Расписание</a>
            <a class="workspace-nav__link" href="/technician/profile">Мой профиль</a>
        </nav>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div>
        <?php endif; ?>
        <?php if (!empty($appointment['is_expired'])): ?>
            <div class="alert alert--warning" role="status"><span class="alert__marker" aria-hidden="true"></span><p>Срок удержания истёк. Слот больше не занят; подтверждение этой заявки недоступно.</p></div>
        <?php endif; ?>

        <div class="appointment-detail-layout">
            <div class="appointment-detail-main">
                <section class="calendar-panel" data-reveal>
                    <div class="calendar-panel__head">
                        <div><span class="eyebrow">Карточка</span><h2>Детали встречи</h2></div>
                        <a class="button button--ghost button--small" href="/technician/requests/<?= e((string) (int) ($appointment['request_id'] ?? 0)) ?>">Открыть заявку</a>
                    </div>
                    <dl class="appointment-detail-list">
                        <div><dt>Цель</dt><dd><?= e((string) ($appointment['meeting_type_label'] ?? 'Встреча')) ?></dd></div>
                        <div><dt>Начало</dt><dd><?= e((string) ($appointment['local_starts_at'] ?? '')) ?></dd></div>
                        <div><dt>Окончание</dt><dd><?= e((string) ($appointment['local_ends_at'] ?? '')) ?></dd></div>
                        <div><dt>Клиент</dt><dd><?= e((string) ($appointment['customer_name'] ?? '')) ?></dd></div>
                        <div><dt>Телефон</dt><dd><?= e((string) ($appointment['customer_phone'] ?? 'Не указан')) ?></dd></div>
                        <div><dt>Email</dt><dd><?= e((string) ($appointment['customer_email'] ?? 'Не указан')) ?></dd></div>
                        <div><dt>Заявка</dt><dd><?= e((string) ($appointment['request_number'] ?? '')) ?> · <?= e((string) ($appointment['request_status'] ?? '')) ?></dd></div>
                        <?php if ((string) ($appointment['customer_comment'] ?? '') !== ''): ?>
                            <div><dt>Комментарий клиента</dt><dd><?= nl2br(e((string) $appointment['customer_comment'])) ?></dd></div>
                        <?php endif; ?>
                        <?php if ((string) ($appointment['technician_note'] ?? '') !== ''): ?>
                            <div><dt>Личная заметка</dt><dd><?= nl2br(e((string) $appointment['technician_note'])) ?></dd></div>
                        <?php endif; ?>
                    </dl>

                    <?php if ((string) ($appointment['status'] ?? '') === 'reschedule_proposed' && !empty($appointment['proposed_starts_at_utc'])): ?>
                        <div class="appointment-proposal">
                            <span class="eyebrow">Предложен перенос</span>
                            <h3><?= e((string) ($appointment['proposed_local_starts_at'] ?? '')) ?></h3>
                            <p><?= e((string) ($appointment['proposed_by_name'] ?? 'Участник')) ?> предложил новое время. Исходный слот: <?= e((string) ($appointment['local_starts_at'] ?? '')) ?>.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if (!empty($permissions['can_confirm']) || !empty($permissions['can_decline'])): ?>
                    <section class="calendar-panel" data-reveal>
                        <div class="calendar-panel__head">
                            <div><span class="eyebrow">Решение</span><h2>Подтвердить или отклонить</h2></div>
                        </div>
                        <div class="appointment-action-row">
                            <?php if (!empty($permissions['can_confirm'])): ?>
                                <form method="post" action="/technician/appointments/<?= e((string) $appointmentId) ?>/confirm">
                                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                    <button class="button button--primary" type="submit">Подтвердить встречу</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($permissions['can_decline'])): ?>
                            <form class="appointment-cancel-form" method="post" action="/technician/appointments/<?= e((string) $appointmentId) ?>/decline">
                                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                <label class="field<?= isset($errors['reason']) ? ' field--invalid' : '' ?>">
                                    <span class="field__label">Причина отказа</span>
                                    <textarea name="reason" rows="2" maxlength="1000" required placeholder="Например, это время больше недоступно"><?= e((string) ($old['reason'] ?? '')) ?></textarea>
                                    <?php if (isset($errors['reason'])): ?><small class="field__error"><?= e((string) $errors['reason']) ?></small><?php endif; ?>
                                </label>
                                <button class="button button--ghost" type="submit">Отклонить заявку</button>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if (!empty($permissions['can_propose'])): ?>
                    <section class="calendar-panel" data-reveal>
                        <div class="calendar-panel__head">
                            <div><span class="eyebrow">Новое время</span><h2>Предложить перенос</h2></div>
                        </div>
                        <form class="appointment-inline-filter" method="get" action="/technician/appointments/<?= e((string) $appointmentId) ?>">
                            <label class="field">
                                <span class="field__label">Дата для поиска слотов</span>
                                <input type="date" name="date" value="<?= e($rescheduleDate) ?>">
                            </label>
                            <button class="button button--secondary button--small" type="submit">Показать слоты</button>
                        </form>
                        <?php if ($rescheduleSlots === []): ?>
                            <p class="appointment-empty">На выбранную дату свободных слотов нет.</p>
                        <?php else: ?>
                            <form class="appointment-booking-form" method="post" action="/technician/appointments/<?= e((string) $appointmentId) ?>/reschedule">
                                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                <input type="hidden" name="date" value="<?= e($rescheduleDate) ?>">
                                <div class="appointment-slot-grid">
                                    <?php foreach ($rescheduleSlots as $slot): ?>
                                        <?php $slotValue = (string) ($slot['start_utc'] ?? ''); $slotId = 'tech-reschedule-' . md5($slotValue); ?>
                                        <label class="appointment-slot<?= $selectedStart === $slotValue ? ' is-selected' : '' ?>" for="<?= e($slotId) ?>">
                                            <input id="<?= e($slotId) ?>" type="radio" name="start_utc" value="<?= e($slotValue) ?>"<?= $selectedStart === $slotValue ? ' checked' : '' ?>>
                                            <span><?= e((string) ($slot['label'] ?? '')) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (isset($errors['start_utc'])): ?><small class="field__error"><?= e((string) $errors['start_utc']) ?></small><?php endif; ?>
                                <label class="field">
                                    <span class="field__label">Комментарий клиенту <span class="field__optional">необязательно</span></span>
                                    <textarea name="note" rows="2" maxlength="1000" placeholder="Почему это время удобнее"><?= e((string) ($old['note'] ?? '')) ?></textarea>
                                </label>
                                <div class="calendar-form-actions">
                                    <button class="button button--primary" type="submit">Предложить клиенту</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if (!empty($permissions['can_complete']) || !empty($permissions['can_no_show']) || !empty($permissions['can_cancel'])): ?>
                    <section class="calendar-panel" data-reveal>
                        <div class="calendar-panel__head">
                            <div><span class="eyebrow">Итог</span><h2>Закрыть встречу</h2></div>
                        </div>
                        <div class="appointment-action-row">
                            <?php if (!empty($permissions['can_complete'])): ?>
                                <form method="post" action="/technician/appointments/<?= e((string) $appointmentId) ?>/complete">
                                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                    <button class="button button--primary" type="submit">Завершить встречу</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!empty($permissions['can_no_show'])): ?>
                                <form method="post" action="/technician/appointments/<?= e((string) $appointmentId) ?>/no-show">
                                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                    <button class="button button--ghost" type="submit">Клиент не пришёл</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($permissions['can_cancel'])): ?>
                            <form class="appointment-cancel-form" method="post" action="/technician/appointments/<?= e((string) $appointmentId) ?>/cancel">
                                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                <label class="field">
                                    <span class="field__label">Причина отмены <span class="field__optional">необязательно</span></span>
                                    <textarea name="reason" rows="2" maxlength="1000"><?= e((string) ($old['reason'] ?? '')) ?></textarea>
                                </label>
                                <button class="button button--ghost" type="submit">Отменить встречу</button>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="appointment-detail-aside">
                <section class="calendar-summary-card" data-reveal>
                    <span class="eyebrow">Журнал</span>
                    <h2>История изменений</h2>
                    <?php if ($events === []): ?>
                        <p class="calendar-aside__empty">Событий пока нет.</p>
                    <?php else: ?>
                        <ol class="appointment-timeline">
                            <?php foreach (array_reverse($events) as $event): ?>
                                <li class="appointment-timeline__item">
                                    <span class="appointment-timeline__dot" aria-hidden="true"></span>
                                    <div>
                                        <strong><?= e((string) ($event['action_label'] ?? '')) ?></strong>
                                        <small><?= e((string) ($event['created_local'] ?? '')) ?><?php if ((string) ($event['actor_name'] ?? '') !== ''): ?> · <?= e((string) $event['actor_name']) ?><?php endif; ?></small>
                                        <?php if ((string) ($event['previous_label'] ?? '') !== '' && (string) ($event['new_label'] ?? '') !== ''): ?>
                                            <p><?= e((string) $event['previous_label']) ?> → <?= e((string) $event['new_label']) ?></p>
                                        <?php endif; ?>
                                        <?php if ((string) ($event['note'] ?? '') !== ''): ?><p><?= nl2br(e((string) $event['note'])) ?></p><?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
