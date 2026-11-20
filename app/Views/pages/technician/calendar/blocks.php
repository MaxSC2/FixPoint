<?php
declare(strict_types=1);

$blocks = isset($blocks) && is_array($blocks) ? array_values(array_filter($blocks, 'is_array')) : [];
$blockKinds = isset($blockKinds) && is_array($blockKinds) ? $blockKinds : [];
$timezone = isset($timezone) ? (string) $timezone : 'UTC';
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$defaultDate = date('Y-m-d');
$startDate = (string) ($old['start_date'] ?? $defaultDate);
$endDate = (string) ($old['end_date'] ?? $startDate);
$startTime = (string) ($old['start_time'] ?? '12:00');
$endTime = (string) ($old['end_time'] ?? '13:00');
$kind = (string) ($old['kind'] ?? 'personal');
$title = (string) ($old['title'] ?? '');
$notes = (string) ($old['notes'] ?? '');
$allDay = in_array((string) ($old['all_day'] ?? ''), ['1', 'on'], true);
$pageTitle = 'Блокировки календаря';
$activeNav = 'technician';
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<link rel="stylesheet" href="/assets/css/calendar.css">
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div>
                <span class="eyebrow">Календарь / Личное время</span>
                <h1>Блокировки</h1>
                <p>Отпуск, выходной, личная занятость или другая недоступность. Клиенты увидят только то, что время занято, без названия и заметки.</p>
                <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/technician/calendar">Календарь</a><span aria-hidden="true">/</span><span aria-current="page">Блокировки</span></nav>
            </div>
            <div class="staff-heading-note"><span>Пояс</span><strong class="calendar-heading-zone"><?= e($timezone) ?></strong><small>приватные заметки</small></div>
        </div>

        <nav class="workspace-nav" aria-label="Разделы мастера" data-reveal>
            <a class="workspace-nav__link" href="/technician/requests">Очередь</a>
            <a class="workspace-nav__link" href="/technician/calendar">Календарь</a>
            <a class="workspace-nav__link is-active" href="/technician/calendar/blocks" aria-current="page">Блокировки</a>
            <a class="workspace-nav__link" href="/technician/calendar/settings">Расписание</a>
            <a class="workspace-nav__link" href="/technician/profile">Мой профиль</a>
        </nav>

        <?php if (isset($errors['general']) || isset($errors['block'])): ?>
            <div class="alert alert--error" role="alert">
                <span class="alert__marker" aria-hidden="true"></span>
                <p><?= e((string) ($errors['general'] ?? $errors['block'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="calendar-blocks-layout">
            <section class="calendar-panel" data-reveal>
                <div class="calendar-panel__head">
                    <div><span class="eyebrow">Новая блокировка</span><h2>Закрыть время</h2></div>
                </div>
                <form class="calendar-block-form" method="post" action="/technician/calendar/blocks">
                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">

                    <label class="field<?= isset($errors['kind']) ? ' field--invalid' : '' ?>">
                        <span class="field__label">Тип</span>
                        <select name="kind">
                            <?php foreach ($blockKinds as $value => $label): ?>
                                <option value="<?= e((string) $value) ?>"<?= $kind === (string) $value ? ' selected' : '' ?>><?= e((string) $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['kind'])): ?><small class="field__error"><?= e((string) $errors['kind']) ?></small><?php endif; ?>
                    </label>

                    <label class="field<?= isset($errors['title']) ? ' field--invalid' : '' ?>">
                        <span class="field__label">Название <span class="field__required" aria-hidden="true">*</span></span>
                        <input type="text" name="title" maxlength="160" value="<?= e($title) ?>" placeholder="Например, отпуск или приём врача" required>
                        <small class="field__help">Название видно только вам.</small>
                        <?php if (isset($errors['title'])): ?><small class="field__error"><?= e((string) $errors['title']) ?></small><?php endif; ?>
                    </label>

                    <div class="calendar-block-form__dates">
                        <label class="field<?= isset($errors['start_date']) ? ' field--invalid' : '' ?>">
                            <span class="field__label">С даты</span>
                            <input type="date" name="start_date" value="<?= e($startDate) ?>" required>
                            <?php if (isset($errors['start_date'])): ?><small class="field__error"><?= e((string) $errors['start_date']) ?></small><?php endif; ?>
                        </label>
                        <label class="field<?= isset($errors['end_date']) ? ' field--invalid' : '' ?>">
                            <span class="field__label">По дату</span>
                            <input type="date" name="end_date" value="<?= e($endDate) ?>" required>
                            <?php if (isset($errors['end_date'])): ?><small class="field__error"><?= e((string) $errors['end_date']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <div class="calendar-block-form__times">
                        <label class="field<?= isset($errors['start_time']) ? ' field--invalid' : '' ?>">
                            <span class="field__label">С времени</span>
                            <input type="time" name="start_time" value="<?= e($startTime) ?>">
                            <?php if (isset($errors['start_time'])): ?><small class="field__error"><?= e((string) $errors['start_time']) ?></small><?php endif; ?>
                        </label>
                        <label class="field<?= isset($errors['end_time']) ? ' field--invalid' : '' ?>">
                            <span class="field__label">До времени</span>
                            <input type="time" name="end_time" value="<?= e($endTime) ?>">
                            <?php if (isset($errors['end_time'])): ?><small class="field__error"><?= e((string) $errors['end_time']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <label class="checkbox-field">
                        <input type="checkbox" name="all_day" value="1"<?= $allDay ? ' checked' : '' ?>>
                        <span>Весь день (время игнорируется)</span>
                    </label>

                    <label class="field<?= isset($errors['notes']) ? ' field--invalid' : '' ?>">
                        <span class="field__label">Заметка <span class="field__optional">необязательно</span></span>
                        <textarea name="notes" maxlength="2000" rows="3" placeholder="Внутренняя заметка, клиентам не показывается"><?= e($notes) ?></textarea>
                        <?php if (isset($errors['notes'])): ?><small class="field__error"><?= e((string) $errors['notes']) ?></small><?php endif; ?>
                    </label>

                    <div class="calendar-form-actions">
                        <button class="button button--primary" type="submit">Добавить блокировку</button>
                    </div>
                </form>
            </section>

            <section class="calendar-panel" data-reveal>
                <div class="calendar-panel__head">
                    <div><span class="eyebrow">Расписание</span><h2>Закрытые интервалы</h2></div>
                    <span class="calendar-panel__hint"><?= e((string) count($blocks)) ?> всего</span>
                </div>
                <?php if ($blocks === []): ?>
                    <div class="calendar-empty">
                        <span aria-hidden="true">◷</span>
                        <h3>Блокировок пока нет</h3>
                        <p>Добавьте отпуск или личную занятость, чтобы клиенты не могли записаться на это время.</p>
                    </div>
                <?php else: ?>
                    <div class="calendar-block-list">
                        <?php foreach ($blocks as $block): ?>
                            <?php $blockId = (int) ($block['id'] ?? 0); ?>
                            <article class="calendar-block-card">
                                <div class="calendar-block-card__marker calendar-block-card__marker--<?= e((string) ($block['kind'] ?? 'other')) ?>" aria-hidden="true"></div>
                                <div class="calendar-block-card__body">
                                    <div class="calendar-block-card__head">
                                        <strong><?= e((string) ($block['title'] ?? 'Блокировка')) ?></strong>
                                        <span><?= e((string) ($block['kind_label'] ?? 'Блокировка')) ?></span>
                                    </div>
                                    <p class="calendar-block-card__when">
                                        <?= e((string) ($block['local_start_date'] ?? '')) ?><?php if ((string) ($block['local_end_date'] ?? '') !== (string) ($block['local_start_date'] ?? '')): ?> — <?= e((string) $block['local_end_date']) ?><?php endif; ?>
                                        · <?= e((string) ($block['local_time'] ?? '')) ?>
                                    </p>
                                    <?php if ((string) ($block['notes'] ?? '') !== ''): ?>
                                        <p class="calendar-block-card__note"><?= nl2br(e((string) $block['notes'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <form method="post" action="/technician/calendar/blocks/<?= e((string) $blockId) ?>/delete" class="calendar-block-card__delete">
                                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                    <button class="button button--ghost button--small" type="submit">Удалить</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
