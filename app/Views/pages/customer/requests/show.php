<?php
declare(strict_types=1);

$request = isset($request) && is_array($request) ? $request : [];
$permissions = isset($permissions) && is_array($permissions) ? $permissions : [];
$history = isset($history) && is_array($history) ? array_values(array_filter($history, 'is_array')) : [];
$comments = isset($comments) && is_array($comments) ? array_values(array_filter($comments, 'is_array')) : [];
$requestId = (string) ($request['id'] ?? '');
$publicNumber = (string) ($request['public_number'] ?? 'Заявка');
$deviceType = (string) ($request['device_type'] ?? 'Устройство');
$deviceBrand = trim((string) ($request['device_brand'] ?? ''));
$deviceModel = trim((string) ($request['device_model'] ?? ''));
$deviceTitle = trim(implode(' ', array_filter([$deviceBrand, $deviceModel])));
if ($deviceTitle === '') {
    $deviceTitle = $deviceType;
}
$requestStatus = (string) ($request['status'] ?? '');
$problemDescription = (string) ($request['problem_description'] ?? '');
$diagnosticResult = (string) ($request['diagnostic_result'] ?? '');
$estimatedCost = $request['estimated_cost'] ?? null;
$finalCost = $request['final_cost'] ?? null;
$estimatedReadyAt = (string) ($request['estimated_ready_at'] ?? '');
$customerContact = (string) ($request['customer_contact'] ?? '');
$createdAt = (string) ($request['created_at'] ?? '');
$technician = isset($request['technician']) && is_array($request['technician']) ? $request['technician'] : null;
$service = isset($request['service']) && is_array($request['service']) ? $request['service'] : null;
$priorityLabels = [
    'low' => 'Низкий',
    'normal' => 'Обычный',
    'high' => 'Высокий',
    'urgent' => 'Срочный',
];
$priorityLabel = $priorityLabels[(string) ($request['priority'] ?? 'normal')] ?? 'Не указан';
$canComment = !empty($permissions['can_comment']);
$canDecideEstimate = !empty($permissions['can_decide_estimate']);
$canDecideEstimate = $canDecideEstimate && $requestStatus === 'awaiting_customer_approval';
$pageTitle = $publicNumber . ' — FixPoint';
$activeNav = 'requests';

$formatMoney = static function (mixed $amount): string {
    if (!is_numeric($amount) || (float) $amount < 0) {
        return 'Не определена';
    }

    return number_format((float) $amount, 0, ',', ' ') . ' ₸';
};
$formatDate = static function (string $value, bool $withTime = false): string {
    if ($value === '') {
        return 'Не указано';
    }
    try {
        $date = new DateTimeImmutable($value);
        return $date->format($withTime ? 'd.m.Y · H:i' : 'd.m.Y');
    } catch (Throwable) {
        return 'Дата уточняется';
    }
};
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--detail" data-reveal>
            <div>
                <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/account">Личный кабинет</a><span aria-hidden="true">/</span><a href="/requests">Мои заявки</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($publicNumber) ?></span></nav>
                <span class="eyebrow">Карточка ремонта / FixPoint</span>
                <h1><?= e($deviceTitle) ?></h1>
                <p><?= e($publicNumber) ?> · <?= e($deviceType) ?></p>
            </div>
            <div class="request-detail-status">
                <span class="request-detail-status__label">Текущий статус</span>
                <?php $status = $requestStatus; require dirname(__DIR__, 3) . '/components/status-badge.php'; ?>
                <span class="request-detail-status__date">Создана <?= e($formatDate($createdAt)) ?></span>
            </div>
        </div>

        <nav class="workspace-nav" aria-label="Разделы личного кабинета" data-reveal>
            <a class="workspace-nav__link" href="/account">Обзор</a>
            <a class="workspace-nav__link is-active" href="/requests" aria-current="page">Мои заявки</a>
            <a class="workspace-nav__link workspace-nav__link--action" href="/requests/create">Создать обращение <span aria-hidden="true">+</span></a>
        </nav>

        <div class="request-detail-grid">
            <div class="request-detail-main">
                <section class="request-detail-panel" data-reveal>
                    <div class="request-detail-panel__head"><div><span class="eyebrow">Описание обращения</span><h2>Что сообщил клиент</h2></div></div>
                    <p class="request-detail-problem"><?= nl2br(e($problemDescription !== '' ? $problemDescription : 'Описание не предоставлено.')) ?></p>
                    <dl class="request-facts">
                        <div><dt>Устройство</dt><dd><?= e($deviceType) ?></dd></div>
                        <div><dt>Производитель</dt><dd><?= e($deviceBrand !== '' ? $deviceBrand : 'Не указан') ?></dd></div>
                        <div><dt>Модель</dt><dd><?= e($deviceModel !== '' ? $deviceModel : 'Не указана') ?></dd></div>
                        <div><dt>Услуга</dt><dd><?= e((string) ($service['name'] ?? 'Диагностика')) ?></dd></div>
                        <div><dt>Контакт</dt><dd><?= e($customerContact !== '' ? $customerContact : 'Не указан') ?></dd></div>
                        <div><dt>Мастер</dt><dd><?= e((string) ($technician['full_name'] ?? 'Пока не назначен')) ?></dd></div>
                    </dl>
                </section>

                <section class="request-detail-panel" data-reveal>
                    <div class="request-detail-panel__head"><div><span class="eyebrow">Заключение мастера</span><h2>Диагностика и смета</h2></div></div>
                    <?php if ($diagnosticResult !== ''): ?>
                        <div class="request-diagnosis"><?= nl2br(e($diagnosticResult)) ?></div>
                    <?php else: ?>
                        <div class="request-pending-note"><span aria-hidden="true">↗</span><div><strong>Диагностика ещё не опубликована</strong><p>Здесь появится вывод мастера, когда проверка устройства будет завершена.</p></div></div>
                    <?php endif; ?>
                    <div class="estimate-grid">
                        <div><span>Предварительная стоимость</span><strong><?= e($formatMoney($estimatedCost)) ?></strong></div>
                        <div><span>Итоговая стоимость</span><strong><?= e($formatMoney($finalCost)) ?></strong></div>
                        <div><span>Ориентир готовности</span><strong><?= e($formatDate($estimatedReadyAt)) ?></strong></div>
                    </div>

                    <?php if ($canDecideEstimate): ?>
                        <div class="estimate-decision">
                            <div><span class="eyebrow">Требуется ваше решение</span><h3>Согласовать предложенную смету?</h3><p>Работы начнутся после подтверждения. При отказе заявка вернётся мастеру для дальнейшего решения.</p></div>
                            <form method="post" action="/requests/<?= e(rawurlencode($requestId)) ?>/estimate-decision">
                                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                <div class="estimate-decision__actions">
                                    <button class="button button--primary" type="submit" name="decision" value="approve">Согласовать</button>
                                    <button class="button button--secondary" type="submit" name="decision" value="reject">Отклонить</button>
                                </div>
                            </form>
                        </div>
                    <?php elseif ($requestStatus === 'awaiting_customer_approval'): ?>
                        <p class="workspace-footnote">Решение по смете уже отправлено или ожидает обновления. Актуальное состояние будет показано после обновления заявки.</p>
                    <?php endif; ?>
                </section>

                <section class="request-detail-panel" data-reveal>
                    <div class="request-detail-panel__head"><div><span class="eyebrow">История заявки</span><h2>Этапы и события</h2></div></div>
                    <?php if ($history !== []): ?>
                        <ol class="request-event-list">
                            <?php foreach ($history as $event): ?>
                                <li class="request-event">
                                    <span class="request-event__marker" aria-hidden="true"></span>
                                    <div class="request-event__body">
                                        <div class="request-event__top">
                                            <strong><?php $status = (string) ($event['to_status'] ?? ''); require dirname(__DIR__, 3) . '/components/status-badge.php'; ?></strong>
                                            <time><?= e($formatDate((string) ($event['changed_at'] ?? ''), true)) ?></time>
                                        </div>
                                        <?php if (!empty($event['reason'])): ?><p><?= e((string) $event['reason']) ?></p><?php endif; ?>
                                        <small><?= e((string) ($event['changed_by_name'] ?? 'Система')) ?></small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <div class="request-pending-note"><span aria-hidden="true">↗</span><div><strong>История пока пуста</strong><p>События появятся после первого изменения статуса заявки.</p></div></div>
                    <?php endif; ?>
                </section>

                <section class="request-detail-panel" data-reveal>
                    <div class="request-detail-panel__head"><div><span class="eyebrow">Обсуждение</span><h2>Комментарии</h2></div></div>
                    <?php
                    $visibleComments = array_values(array_filter(
                        $comments,
                        static fn (array $comment): bool => empty($comment['is_internal'])
                    ));
                    ?>
                    <?php if ($visibleComments !== []): ?>
                        <div class="request-comments">
                            <?php foreach ($visibleComments as $comment): ?>
                                <article class="request-comment">
                                    <div class="request-comment__top"><strong><?= e((string) ($comment['author_name'] ?? 'Участник заявки')) ?></strong><time><?= e($formatDate((string) ($comment['created_at'] ?? ''), true)) ?></time></div>
                                    <p><?= nl2br(e((string) ($comment['body'] ?? ''))) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="request-comments__empty">Пока нет комментариев. Здесь появятся сообщения по обращению.</p>
                    <?php endif; ?>

                    <?php if ($canComment): ?>
                        <form class="request-comment-form" method="post" action="/requests/<?= e(rawurlencode($requestId)) ?>/comments">
                            <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                            <label class="field<?= isset($errors['body']) ? ' field--invalid' : '' ?>" for="request-comment-body">
                                <span class="field__label">Добавить комментарий</span>
                                <textarea id="request-comment-body" name="body" rows="3" maxlength="3000" placeholder="Напишите уточнение или задайте вопрос" required <?= isset($errors['body']) ? 'aria-invalid="true" aria-describedby="request-comment-error"' : '' ?>></textarea>
                                <?php if (isset($errors['body'])): ?><small class="field__error" id="request-comment-error"><?= e((string) $errors['body']) ?></small><?php endif; ?>
                            </label>
                            <button class="button button--primary" type="submit">Отправить комментарий</button>
                        </form>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="request-detail-aside" data-reveal>
                <div class="request-aside-card">
                    <span class="eyebrow">Кратко о заявке</span>
                    <h2><?= e($publicNumber) ?></h2>
                    <div class="request-aside-card__row"><span>Приоритет</span><strong><?= e($priorityLabel) ?></strong></div>
                    <div class="request-aside-card__row"><span>Создана</span><strong><?= e($formatDate($createdAt)) ?></strong></div>
                    <div class="request-aside-card__row"><span>Обновлена</span><strong><?= e($formatDate((string) ($request['updated_at'] ?? ''))) ?></strong></div>
                    <a class="button button--secondary" href="/requests">← К списку заявок</a>
                </div>
                <div class="request-aside-help">
                    <span aria-hidden="true">i</span>
                    <p>Не передавайте пароли от устройств и банковские данные в комментариях. Для уточнений используйте контакт, указанный в заявке.</p>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
