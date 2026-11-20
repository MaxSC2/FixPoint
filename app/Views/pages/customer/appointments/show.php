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
$isExpired = !empty($appointment['is_expired']);
$pageTitle = 'Встреча — ' . (string) ($appointment['request_number'] ?? '') ;
$activeNav = 'requests';
$selectedStart = (string) ($old['start_utc'] ?? '');
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<link rel="stylesheet" href="/assets/css/calendar.css">
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--detail" data-reveal>
            <div>
                <nav class="breadcrumbs" aria-label="Хлебные крошки">
                    <a href="/account">Личный кабинет</a><span aria-hidden="true">/</span>
                    <a href="/appointments">Мои встречи</a><span aria-hidden="true">/</span>
                    <span aria-current="page"><?= e((string) ($appointment['request_number'] ?? 'Встреча')) ?></span>
                </nav>
                <span class="eyebrow">Встреча / <?= e((string) ($appointment['meeting_type_label'] ?? '')) ?></span>
                <h1><?= e((string) ($appointment['local_date'] ?? '')) ?>, <?= e((string) ($appointment['local_time'] ?? '')) ?></h1>
                <p><?= e((string) ($appointment['device_title'] ?? '')) ?> · <?= e((string) ($appointment['request_number'] ?? '')) ?></p>
            </div>
            <div class="request-detail-status">
                <span class="request-detail-status__label">Статус встречи</span>
                <span class="calendar-status calendar-status--<?= e($statusTone) ?> calendar-status--large"><?= e((string) ($appointment['status_label'] ?? '')) ?></span>
                <small>Часовой пояс: <?= e((string) ($appointment['timezone'] ?? 'UTC')) ?></small>
            </div>
        </div>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div>
        <?php endif; ?>
        <?php if ($isExpired): ?>
            <div class="alert alert--warning" role="status"><span class="alert__marker" aria-hidden="true"></span><p>Срок удержания этого времени истёк. Запись больше не блокирует слот; вы можете выбрать новое время по заявке.</p></div>
        <?php endif; ?>

        <div class="appointment-detail-layout">
            <div class="appointment-detail-main">
                <section class="calendar-panel" data-reveal>
                    <div class="calendar-panel__head">
                        <div><span class="eyebrow">Детали</span><h2>Когда и зачем</h2></div>
                    </div>
                    <dl class="appointment-detail-list">
                        <div><dt>Цель</dt><dd><?= e((string) ($appointment['meeting_type_label'] ?? 'Встреча')) ?></dd></div>
                        <div><dt>Начало</dt><dd><?= e((string) ($appointment['local_starts_at'] ?? '')) ?></dd></div>
                        <div><dt>Окончание</dt><dd><?= e((string) ($appointment['local_ends_at'] ?? '')) ?></dd></div>
                        <div><dt>Мастер</dt><dd><?= e((string) ($appointment['technician_name'] ?? '')) ?></dd></div>
                        <div><dt>Заявка</dt><dd><a href="/requests/<?= e((string) (int) ($appointment['request_id'] ?? 0)) ?>"><?= e((string) ($appointment['request_number'] ?? '')) ?></a></dd></div>
                        <?php if ((string) ($appointment['customer_comment'] ?? '') !== ''): ?>
                            <div><dt>Ваш комментарий</dt><dd><?= nl2br(e((string) $appointment['customer_comment'])) ?></dd></div>
                        <?php endif; ?>
                    </dl>
                    <?php if ((string) ($appointment['status'] ?? '') === 'reschedule_proposed' && !empty($appointment['proposed_starts_at_utc'])): ?>
                        <div class="appointment-proposal">
                            <span class="eyebrow">Предложен перенос</span>
                            <h3><?= e((string) ($appointment['proposed_local_starts_at'] ?? '')) ?></h3>
                            <p><?= e((string) ($appointment['proposed_by_name'] ?? 'Участник')) ?> предлагает новое время. Подтвердите или отклоните его.</p>
                            <?php if (!empty($permissions['can_respond'])): ?>
                                <div class="appointment-proposal__actions">
                                    <form method="post" action="/appointments/<?= e((string) $appointmentId) ?>/proposal">
                                        <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                        <input type="hidden" name="decision" value="accept">
                                        <button class="button button--primary button--small" type="submit">Принять перенос</button>
                                    </form>
                                    <form method="post" action="/appointments/<?= e((string) $appointmentId) ?>/proposal">
                                        <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                        <input type="hidden" name="decision" value="decline">
                                        <button class="button button--ghost button--small" type="submit">Отклонить</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if (!empty($permissions['can_propose'])): ?>
                    <section class="calendar-panel" data-reveal>
                        <div class="calendar-panel__head">
                            <div><span class="eyebrow">Новое время</span><h2>Предложить перенос</h2></div>
                        </div>
                        <form class="appointment-inline-filter" method="get" action="/appointments/<?= e((string) $appointmentId) ?>">
                            <label class="field">
                                <span class="field__label">Дата для поиска слотов</span>
                                <input type="date" name="date" value="<?= e($rescheduleDate) ?>">
                            </label>
                            <button class="button button--secondary button--small" type="submit">Показать слоты</button>
                        </form>
                        <?php if ($rescheduleSlots === []): ?>
                            <p class="appointment-empty">На выбранную дату свободных слотов нет. Попробуйте другую дату.</p>
                        <?php else: ?>
                            <form class="appointment-booking-form" method="post" action="/appointments/<?= e((string) $appointmentId) ?>/reschedule">
                                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                <input type="hidden" name="date" value="<?= e($rescheduleDate) ?>">
                                <div class="appointment-slot-grid">
                                    <?php foreach ($rescheduleSlots as $slot): ?>
                                        <?php $slotValue = (string) ($slot['start_utc'] ?? ''); $slotId = 'reschedule-' . md5($slotValue); ?>
                                        <label class="appointment-slot<?= $selectedStart === $slotValue ? ' is-selected' : '' ?>" for="<?= e($slotId) ?>">
                                            <input id="<?= e($slotId) ?>" type="radio" name="start_utc" value="<?= e($slotValue) ?>"<?= $selectedStart === $slotValue ? ' checked' : '' ?>>
                                            <span><?= e((string) ($slot['label'] ?? '')) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (isset($errors['start_utc'])): ?><small class="field__error"><?= e((string) $errors['start_utc']) ?></small><?php endif; ?>
                                <label class="field">
                                    <span class="field__label">Комментарий <span class="field__optional">необязательно</span></span>
                                    <textarea name="note" rows="2" maxlength="1000" placeholder="Почему удобнее другое время"><?= e((string) ($old['note'] ?? '')) ?></textarea>
                                </label>
                                <div class="calendar-form-actions">
                                    <button class="button button--primary" type="submit">Отправить мастеру</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if (!empty($permissions['can_cancel'])): ?>
                    <section class="calendar-panel" data-reveal>
                        <div class="calendar-panel__head">
                            <div><span class="eyebrow">Отмена</span><h2>Не сможете прийти?</h2></div>
                        </div>
                        <form class="appointment-cancel-form" method="post" action="/appointments/<?= e((string) $appointmentId) ?>/cancel">
                            <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                            <label class="field">
                                <span class="field__label">Причина <span class="field__optional">необязательно</span></span>
                                <textarea name="reason" rows="2" maxlength="1000" placeholder="Коротко объясните отмену"><?= e((string) ($old['reason'] ?? '')) ?></textarea>
                            </label>
                            <button class="button button--ghost" type="submit">Отменить встречу</button>
                            <p class="field__help">Отмена подтверждённой встречи возможна не позже допустимого срока до начала. После отмены слот освобождается.</p>
                        </form>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="appointment-detail-aside">
                <section class="calendar-summary-card" data-reveal>
                    <span class="eyebrow">Ход встречи</span>
                    <h2>История</h2>
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
