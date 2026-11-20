<?php
declare(strict_types=1);

$appointmentList = isset($appointmentList) && is_array($appointmentList) ? $appointmentList : [];
$upcoming = isset($appointmentList['upcoming']) && is_array($appointmentList['upcoming'])
    ? array_values(array_filter($appointmentList['upcoming'], 'is_array'))
    : [];
$history = isset($appointmentList['history']) && is_array($appointmentList['history'])
    ? array_values(array_filter($appointmentList['history'], 'is_array'))
    : [];
$pageTitle = 'Мои встречи';
$activeNav = 'requests';
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<link rel="stylesheet" href="/assets/css/calendar.css">
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div>
                <span class="eyebrow">Личный кабинет / Встречи</span>
                <h1>Мои встречи</h1>
                <p>Запись на передачу устройства, получение и консультацию. Время показано в часовом поясе мастера.</p>
                <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/account">Личный кабинет</a><span aria-hidden="true">/</span><span aria-current="page">Встречи</span></nav>
            </div>
            <div class="staff-heading-note"><span>Активные</span><strong><?= e((string) count($upcoming)) ?></strong><small>в истории: <?= e((string) count($history)) ?></small></div>
        </div>

        <section class="calendar-panel" data-reveal>
            <div class="calendar-panel__head">
                <div><span class="eyebrow">Ближайшие</span><h2>Предстоящие встречи</h2></div>
                <a class="button button--secondary button--small" href="/requests">Мои заявки</a>
            </div>
            <?php if ($upcoming === []): ?>
                <div class="calendar-empty">
                    <span aria-hidden="true">◷</span>
                    <h3>Запланированных встреч нет</h3>
                    <p>Откройте заявку и выберите «Записаться на встречу», чтобы согласовать время с мастером.</p>
                </div>
            <?php else: ?>
                <div class="appointment-card-list">
                    <?php foreach ($upcoming as $appointment): ?>
                        <?php $tone = (string) ($appointment['status_tone'] ?? 'neutral'); ?>
                        <article class="appointment-card">
                            <div class="appointment-card__when">
                                <strong><?= e((string) ($appointment['local_date'] ?? '')) ?></strong>
                                <span><?= e((string) ($appointment['local_time'] ?? '')) ?></span>
                            </div>
                            <div class="appointment-card__body">
                                <div class="appointment-card__head">
                                    <h3><?= e((string) ($appointment['meeting_type_label'] ?? 'Встреча')) ?></h3>
                                    <span class="calendar-status calendar-status--<?= e($tone) ?>"><?= e((string) ($appointment['status_label'] ?? '')) ?></span>
                                </div>
                                <p><?= e((string) ($appointment['device_title'] ?? '')) ?> · <?= e((string) ($appointment['request_number'] ?? '')) ?></p>
                                <small>Мастер: <?= e((string) ($appointment['technician_name'] ?? '')) ?></small>
                            </div>
                            <a class="button button--secondary button--small" href="/appointments/<?= e((string) (int) ($appointment['id'] ?? 0)) ?>">Открыть</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($history !== []): ?>
            <section class="calendar-panel" data-reveal>
                <div class="calendar-panel__head">
                    <div><span class="eyebrow">Архив</span><h2>История встреч</h2></div>
                </div>
                <div class="appointment-card-list appointment-card-list--compact">
                    <?php foreach ($history as $appointment): ?>
                        <?php $tone = (string) ($appointment['status_tone'] ?? 'neutral'); ?>
                        <article class="appointment-card">
                            <div class="appointment-card__when">
                                <strong><?= e((string) ($appointment['local_date'] ?? '')) ?></strong>
                                <span><?= e((string) ($appointment['local_time'] ?? '')) ?></span>
                            </div>
                            <div class="appointment-card__body">
                                <div class="appointment-card__head">
                                    <h3><?= e((string) ($appointment['meeting_type_label'] ?? 'Встреча')) ?></h3>
                                    <span class="calendar-status calendar-status--<?= e($tone) ?>"><?= e((string) ($appointment['status_label'] ?? '')) ?></span>
                                </div>
                                <p><?= e((string) ($appointment['device_title'] ?? '')) ?> · <?= e((string) ($appointment['request_number'] ?? '')) ?></p>
                            </div>
                            <a class="button button--ghost button--small" href="/appointments/<?= e((string) (int) ($appointment['id'] ?? 0)) ?>">Открыть</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
