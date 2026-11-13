<?php
declare(strict_types=1);

$request = isset($request) && is_array($request) ? $request : [];
$permissions = isset($permissions) && is_array($permissions) ? $permissions : [];
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$history = isset($history) && is_array($history) ? array_values(array_filter($history, 'is_array')) : [];
$comments = isset($comments) && is_array($comments) ? array_values(array_filter($comments, 'is_array')) : [];
$allowedTransitions = isset($allowedTransitions) && is_array($allowedTransitions) ? $allowedTransitions : [];
$requestId = (string) ($request['id'] ?? '');
$publicNumber = (string) ($request['public_number'] ?? 'Заявка');
$deviceType = (string) ($request['device_type'] ?? 'Устройство');
$deviceTitle = trim(implode(' ', array_filter([(string) ($request['device_brand'] ?? ''), (string) ($request['device_model'] ?? '')])));
if ($deviceTitle === '') {
    $deviceTitle = $deviceType;
}
$customer = isset($request['customer']) && is_array($request['customer']) ? $request['customer'] : [];
$service = isset($request['service']) && is_array($request['service']) ? $request['service'] : [];
$requestStatus = (string) ($request['status'] ?? '');
$canEditDiagnosis = !empty($permissions['can_edit_diagnosis']);
$canChangeStatus = !empty($permissions['can_change_status']) && $allowedTransitions !== [];
$canComment = !empty($permissions['can_comment']);
$diagnosticResult = (string) ($old['diagnostic_result'] ?? ($request['diagnostic_result'] ?? ''));
$estimatedCost = $old['estimated_cost'] ?? ($request['estimated_cost'] ?? '');
$estimatedReadyAt = (string) ($old['estimated_ready_at'] ?? ($request['estimated_ready_at'] ?? ''));
$estimatedReadyInput = $estimatedReadyAt !== '' ? substr(str_replace(' ', 'T', $estimatedReadyAt), 0, 16) : '';
$priorityLabels = ['low' => 'Низкий', 'normal' => 'Обычный', 'high' => 'Высокий', 'urgent' => 'Срочный'];
$statusLabels = [
    'new' => 'Новая',
    'accepted' => 'Принята',
    'diagnostics' => 'Диагностика',
    'awaiting_customer_approval' => 'Ожидает согласования',
    'awaiting_parts' => 'Ожидает запчасти',
    'repairing' => 'В ремонте',
    'ready_for_pickup' => 'Готова к выдаче',
    'completed' => 'Завершена',
    'cancelled' => 'Отменена',
    'rejected' => 'Отклонена',
];
$pageTitle = 'Рабочая карточка ' . $publicNumber;
$activeNav = 'technician';
$formatDate = static function (string $value, bool $withTime = false): string {
    if ($value === '') {
        return 'Не указано';
    }
    try {
        return (new DateTimeImmutable($value))->format($withTime ? 'd.m.Y · H:i' : 'd.m.Y');
    } catch (Throwable) {
        return 'Дата уточняется';
    }
};
$formatMoney = static function (mixed $value): string {
    if (!is_numeric($value) || (float) $value < 0) {
        return 'Не определена';
    }
    return number_format((float) $value, 0, ',', ' ') . ' ₸';
};
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--detail" data-reveal>
            <div>
                <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/technician/requests">Рабочая очередь</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($publicNumber) ?></span></nav>
                <span class="eyebrow">Рабочая карточка / Мастер</span>
                <h1><?= e($deviceTitle) ?></h1>
                <p><?= e($publicNumber) ?> · <?= e((string) ($customer['full_name'] ?? 'Клиент')) ?></p>
            </div>
            <div class="request-detail-status"><span class="request-detail-status__label">Текущий статус</span><?php $status = $requestStatus; require dirname(__DIR__, 3) . '/components/status-badge.php'; ?><span class="request-detail-status__date">Создана <?= e($formatDate((string) ($request['created_at'] ?? ''))) ?></span></div>
        </div>

        <nav class="workspace-nav" aria-label="Разделы мастера" data-reveal>
            <a class="workspace-nav__link is-active" href="/technician/requests" aria-current="page">← К очереди</a>
        </nav>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div>
        <?php endif; ?>

        <div class="tech-detail-grid">
            <div class="tech-detail-main">
                <section class="request-detail-panel" data-reveal>
                    <div class="request-detail-panel__head"><div><span class="eyebrow">Контекст обращения</span><h2>Устройство и клиент</h2></div><span class="priority-chip priority-chip--<?= e(in_array((string) ($request['priority'] ?? 'normal'), ['low','normal','high','urgent'], true) ? (string) ($request['priority'] ?? 'normal') : 'normal') ?>"><?= e($priorityLabels[(string) ($request['priority'] ?? 'normal')] ?? 'Не указан') ?> приоритет</span></div>
                    <p class="request-detail-problem"><?= nl2br(e((string) ($request['problem_description'] ?? 'Описание проблемы не предоставлено.'))) ?></p>
                    <dl class="request-facts">
                        <div><dt>Тип устройства</dt><dd><?= e($deviceType) ?></dd></div>
                        <div><dt>Производитель и модель</dt><dd><?= e($deviceTitle) ?></dd></div>
                        <div><dt>Клиент</dt><dd><?= e((string) ($customer['full_name'] ?? 'Не указан')) ?></dd></div>
                        <div><dt>Контакт</dt><dd><?= e((string) ($request['customer_contact'] ?? 'Не указан')) ?></dd></div>
                        <div><dt>Услуга</dt><dd><?= e((string) ($service['name'] ?? 'Не выбрана')) ?></dd></div>
                        <div><dt>Создана</dt><dd><?= e($formatDate((string) ($request['created_at'] ?? ''))) ?></dd></div>
                    </dl>
                </section>

                <section class="request-detail-panel" data-reveal>
                    <div class="request-detail-panel__head"><div><span class="eyebrow">Заключение и стоимость</span><h2>Диагностика</h2></div></div>
                    <?php if ($canEditDiagnosis): ?>
                        <form class="tech-diagnosis-form" method="post" action="/technician/requests/<?= e(rawurlencode($requestId)) ?>/diagnosis">
                            <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                            <label class="field<?= isset($errors['diagnostic_result']) ? ' field--invalid' : '' ?>" for="technician-diagnosis">
                                <span class="field__label">Результат диагностики <span class="field__required" aria-hidden="true">*</span></span>
                                <textarea id="technician-diagnosis" name="diagnostic_result" rows="6" maxlength="10000" placeholder="Опишите обнаруженную неисправность, проверенные узлы и выводы" required <?= isset($errors['diagnostic_result']) ? 'aria-invalid="true" aria-describedby="technician-diagnosis-error"' : '' ?>><?= e($diagnosticResult) ?></textarea>
                                <?php if (isset($errors['diagnostic_result'])): ?><small class="field__error" id="technician-diagnosis-error"><?= e((string) $errors['diagnostic_result']) ?></small><?php endif; ?>
                            </label>
                            <div class="form-grid">
                                <label class="field<?= isset($errors['estimated_cost']) ? ' field--invalid' : '' ?>" for="technician-estimated-cost">
                                    <span class="field__label">Предварительная стоимость, ₸</span>
                                    <input id="technician-estimated-cost" type="number" name="estimated_cost" min="0" max="99999999.99" step="0.01" value="<?= e((string) $estimatedCost) ?>" placeholder="Например, 15000" <?= isset($errors['estimated_cost']) ? 'aria-invalid="true" aria-describedby="technician-estimated-cost-error"' : '' ?>>
                                    <?php if (isset($errors['estimated_cost'])): ?><small class="field__error" id="technician-estimated-cost-error"><?= e((string) $errors['estimated_cost']) ?></small><?php endif; ?>
                                </label>
                                <label class="field<?= isset($errors['estimated_ready_at']) ? ' field--invalid' : '' ?>" for="technician-estimated-ready">
                                    <span class="field__label">Ориентир готовности</span>
                                    <input id="technician-estimated-ready" type="datetime-local" name="estimated_ready_at" value="<?= e($estimatedReadyInput) ?>" <?= isset($errors['estimated_ready_at']) ? 'aria-invalid="true" aria-describedby="technician-estimated-ready-error"' : '' ?>>
                                    <?php if (isset($errors['estimated_ready_at'])): ?><small class="field__error" id="technician-estimated-ready-error"><?= e((string) $errors['estimated_ready_at']) ?></small><?php endif; ?>
                                </label>
                            </div>
                            <div class="request-form__actions"><button class="button button--primary button--large" type="submit">Сохранить диагностику</button></div>
                        </form>
                    <?php else: ?>
                        <?php if ($diagnosticResult !== ''): ?><div class="request-diagnosis"><?= nl2br(e($diagnosticResult)) ?></div><?php else: ?><div class="request-pending-note"><span aria-hidden="true">↗</span><div><strong>Заключение ещё не заполнено</strong><p>Редактирование недоступно для текущего этапа или вашей роли.</p></div></div><?php endif; ?>
                        <div class="estimate-grid"><div><span>Предварительная стоимость</span><strong><?= e($formatMoney($estimatedCost)) ?></strong></div><div><span>Ориентир готовности</span><strong><?= e($formatDate($estimatedReadyAt)) ?></strong></div></div>
                    <?php endif; ?>
                </section>

                <section class="request-detail-panel" data-reveal>
                    <div class="request-detail-panel__head"><div><span class="eyebrow">Рабочий процесс</span><h2>Обновить статус</h2></div></div>
                    <?php if ($canChangeStatus): ?>
                        <form class="tech-status-form" method="post" action="/technician/requests/<?= e(rawurlencode($requestId)) ?>/status">
                            <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                            <label class="field<?= isset($errors['status']) ? ' field--invalid' : '' ?>" for="technician-next-status">
                                <span class="field__label">Следующий статус</span>
                                <select id="technician-next-status" name="status" required>
                                    <option value="">Выберите допустимый переход</option>
                                    <?php foreach ($allowedTransitions as $transition): ?>
                                        <?php
                                        $transitionCode = is_array($transition) ? (string) ($transition['code'] ?? '') : (string) $transition;
                                        if ($transitionCode === '') { continue; }
                                        $transitionLabel = $statusLabels[$transitionCode] ?? $transitionCode;
                                        ?>
                                        <option value="<?= e($transitionCode) ?>" <?= (string) ($old['status'] ?? '') === $transitionCode ? 'selected' : '' ?>><?= e($transitionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['status'])): ?><small class="field__error"><?= e((string) $errors['status']) ?></small><?php endif; ?>
                            </label>
                            <label class="field" for="technician-status-reason"><span class="field__label">Комментарий к переходу</span><textarea id="technician-status-reason" name="reason" rows="3" maxlength="500" placeholder="Что изменилось на этом этапе?"><?= e((string) ($old['reason'] ?? '')) ?></textarea></label>
                            <button class="button button--primary" type="submit">Сохранить статус</button>
                        </form>
                    <?php else: ?>
                        <p class="request-comments__empty">Для этой заявки сейчас нет доступных переходов статуса или действие недоступно вашей роли.</p>
                    <?php endif; ?>
                </section>

                <section class="request-detail-panel" data-reveal>
                    <div class="request-detail-panel__head"><div><span class="eyebrow">События</span><h2>История заявки</h2></div></div>
                    <?php if ($history !== []): ?>
                        <ol class="request-event-list">
                            <?php foreach ($history as $event): ?>
                                <li class="request-event"><span class="request-event__marker" aria-hidden="true"></span><div class="request-event__body"><div class="request-event__top"><strong><?php $status = (string) ($event['to_status'] ?? ''); require dirname(__DIR__, 3) . '/components/status-badge.php'; ?></strong><time><?= e($formatDate((string) ($event['changed_at'] ?? ''), true)) ?></time></div><?php if (!empty($event['reason'])): ?><p><?= e((string) $event['reason']) ?></p><?php endif; ?><small><?= e((string) ($event['changed_by_name'] ?? 'Система')) ?></small></div></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?><p class="request-comments__empty">История появится после первого изменения заявки.</p><?php endif; ?>
                </section>

                <section class="request-detail-panel" data-reveal>
                    <div class="request-detail-panel__head"><div><span class="eyebrow">Коммуникация</span><h2>Комментарии</h2></div></div>
                    <?php if ($comments !== []): ?>
                        <div class="request-comments">
                            <?php foreach ($comments as $comment): ?>
                                <article class="request-comment<?= !empty($comment['is_internal']) ? ' request-comment--internal' : '' ?>">
                                    <div class="request-comment__top"><strong><?= e((string) ($comment['author_name'] ?? 'Участник')) ?><?php if (!empty($comment['is_internal'])): ?> <span class="internal-note-tag">Внутренняя заметка</span><?php endif; ?></strong><time><?= e($formatDate((string) ($comment['created_at'] ?? ''), true)) ?></time></div>
                                    <p><?= nl2br(e((string) ($comment['body'] ?? ''))) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?><p class="request-comments__empty">Комментариев пока нет.</p><?php endif; ?>

                    <?php if ($canComment): ?>
                        <form class="request-comment-form" method="post" action="/requests/<?= e(rawurlencode($requestId)) ?>/comments">
                            <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                            <label class="field<?= isset($errors['body']) ? ' field--invalid' : '' ?>" for="technician-comment-body"><span class="field__label">Новое сообщение или заметка</span><textarea id="technician-comment-body" name="body" rows="3" maxlength="3000" required><?= e((string) ($old['body'] ?? '')) ?></textarea><?php if (isset($errors['body'])): ?><small class="field__error"><?= e((string) $errors['body']) ?></small><?php endif; ?></label>
                            <label class="checkbox-field"><input type="checkbox" name="is_internal" value="1" <?= !empty($old['is_internal']) ? 'checked' : '' ?>><span>Внутренняя заметка, не видна клиенту</span></label>
                            <button class="button button--primary" type="submit">Добавить комментарий</button>
                        </form>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="request-detail-aside" data-reveal>
                <div class="request-aside-card"><span class="eyebrow">Карточка устройства</span><h2><?= e($publicNumber) ?></h2><div class="request-aside-card__row"><span>Клиент</span><strong><?= e((string) ($customer['full_name'] ?? 'Не указан')) ?></strong></div><div class="request-aside-card__row"><span>Приоритет</span><strong><?= e($priorityLabels[(string) ($request['priority'] ?? 'normal')] ?? 'Не указан') ?></strong></div><div class="request-aside-card__row"><span>Текущий статус</span><strong><?= e($statusLabels[$requestStatus] ?? 'Не указан') ?></strong></div><a class="button button--secondary" href="/technician/requests">← К очереди</a></div>
                <div class="request-aside-help"><span>i</span><p>Проверяйте serial/model и состояние устройства при приёме. Не добавляйте пароли клиентов в публичные комментарии.</p></div>
            </aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
