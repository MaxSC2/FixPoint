<?php
declare(strict_types=1);

$pageTitle = 'Очередь мастера';
$activeNav = 'technician';
$requestList = isset($requestList) && is_array($requestList) ? $requestList : [];
$items = isset($requestList['items']) && is_array($requestList['items']) ? array_values(array_filter($requestList['items'], 'is_array')) : [];
$pagination = isset($requestList['pagination']) && is_array($requestList['pagination']) ? $requestList['pagination'] : [];
$filters = isset($requestList['filters']) && is_array($requestList['filters']) ? $requestList['filters'] : [];
$query = (string) ($filters['q'] ?? '');
$selectedStatus = (string) ($filters['status'] ?? '');
$selectedPriority = (string) ($filters['priority'] ?? '');
$selectedSort = (string) ($filters['sort'] ?? 'newest');
$currentPage = max(1, (int) ($pagination['current_page'] ?? 1));
$lastPage = max(1, (int) ($pagination['last_page'] ?? 1));
$totalRequests = max(0, (int) ($pagination['total'] ?? count($items)));
$statusOptions = [
    'new' => 'Новые',
    'accepted' => 'Приняты',
    'diagnostics' => 'Диагностика',
    'awaiting_customer_approval' => 'Ожидают согласования',
    'awaiting_parts' => 'Ожидают запчасти',
    'repairing' => 'В ремонте',
    'ready_for_pickup' => 'Готовы к выдаче',
];
$priorityLabels = ['low' => 'Низкий', 'normal' => 'Обычный', 'high' => 'Высокий', 'urgent' => 'Срочный'];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div>
                <span class="eyebrow">Рабочее пространство / Мастер</span>
                <h1>Очередь ремонта</h1>
                <p>Назначенные обращения, диагностические задачи и следующий шаг по каждому устройству.</p>
            </div>
            <div class="staff-heading-note"><span>Очередь</span><strong><?= e((string) $totalRequests) ?></strong><small>заявок в текущей выборке</small></div>
        </div>

        <nav class="workspace-nav" aria-label="Разделы мастера" data-reveal>
            <a class="workspace-nav__link is-active" href="/technician/requests" aria-current="page">Очередь</a>
        </nav>

        <section class="staff-list-panel" data-reveal>
            <div class="request-list-panel__head">
                <div><span class="eyebrow">Назначенные обращения</span><h2>Рабочий список</h2></div>
                <span class="request-list-panel__count"><?= e((string) $totalRequests) ?> всего</span>
            </div>

            <form class="request-filters staff-filters" method="get" action="/technician/requests">
                <label class="field request-filters__search"><span class="sr-only">Поиск заявки</span><input type="search" name="q" value="<?= e($query) ?>" placeholder="Номер, клиент или устройство"></label>
                <label class="field"><span class="sr-only">Статус</span><select name="status"><option value="">Все статусы</option><?php foreach ($statusOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="sr-only">Приоритет</span><select name="priority"><option value="">Любой приоритет</option><?php foreach ($priorityLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $selectedPriority === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="sr-only">Сортировка</span><select name="sort"><option value="newest" <?= $selectedSort === 'newest' ? 'selected' : '' ?>>Сначала новые</option><option value="oldest" <?= $selectedSort === 'oldest' ? 'selected' : '' ?>>Сначала старые</option><option value="priority" <?= $selectedSort === 'priority' ? 'selected' : '' ?>>По приоритету</option></select></label>
                <button class="button button--primary" type="submit">Применить</button>
                <?php if ($query !== '' || $selectedStatus !== '' || $selectedPriority !== '' || $selectedSort !== 'newest'): ?><a class="button button--ghost" href="/technician/requests">Сбросить</a><?php endif; ?>
            </form>

            <?php if ($items !== []): ?>
                <div class="tech-request-grid">
                    <?php foreach ($items as $request): ?>
                        <?php
                        $requestId = (string) ($request['id'] ?? '');
                        $publicNumber = (string) ($request['public_number'] ?? 'Заявка');
                        $deviceType = (string) ($request['device_type'] ?? 'Устройство');
                        $deviceTitle = trim(implode(' ', array_filter([
                            (string) ($request['device_brand'] ?? ''),
                            (string) ($request['device_model'] ?? ''),
                        ])));
                        if ($deviceTitle === '') {
                            $deviceTitle = $deviceType;
                        }
                        $priority = (string) ($request['priority'] ?? 'normal');
                        $priorityLabel = $priorityLabels[$priority] ?? 'Не указан';
                        $customerName = (string) ($request['customer']['full_name'] ?? 'Клиент');
                        $description = trim((string) ($request['problem_description'] ?? ''));
                        $requestHref = '/technician/requests/' . rawurlencode($requestId);
                        ?>
                        <article class="tech-request-card">
                            <div class="tech-request-card__top"><span><?= e($publicNumber) ?></span><?php $status = (string) ($request['status'] ?? ''); require dirname(__DIR__, 3) . '/components/status-badge.php'; ?></div>
                            <div class="tech-request-card__device"><div><span class="tech-request-card__type"><?= e($deviceType) ?></span><h3><?= e($deviceTitle) ?></h3><p><?= e($customerName) ?></p></div><span class="priority-chip priority-chip--<?= e(in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal') ?>"><?= e($priorityLabel) ?></span></div>
                            <p class="tech-request-card__problem"><?= e($description !== '' ? mb_substr($description, 0, 150) . (mb_strlen($description) > 150 ? '…' : '') : 'Описание проблемы не предоставлено.') ?></p>
                            <div class="tech-request-card__bottom"><span>Открыть рабочую карточку</span><a href="<?= e($requestHref) ?>" aria-label="Открыть <?= e($publicNumber) ?>">↗</a></div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($lastPage > 1): ?>
                    <?php
                    $pageQuery = ['q' => $query, 'status' => $selectedStatus, 'priority' => $selectedPriority, 'sort' => $selectedSort];
                    $pageQuery = array_filter($pageQuery, static fn (string $value): bool => $value !== '');
                    $pageUrl = static fn (int $page): string => '/technician/requests?' . http_build_query($pageQuery + ['page' => $page]);
                    ?>
                    <nav class="pagination request-pagination" aria-label="Страницы очереди">
                        <a class="pagination__link" href="<?= e($pageUrl(max(1, $currentPage - 1))) ?>" aria-label="Предыдущая страница" <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>←</a>
                        <span class="request-pagination__label">Страница <?= e((string) $currentPage) ?> из <?= e((string) $lastPage) ?></span>
                        <a class="pagination__link" href="<?= e($pageUrl(min($lastPage, $currentPage + 1))) ?>" aria-label="Следующая страница" <?= $currentPage >= $lastPage ? 'aria-disabled="true" tabindex="-1"' : '' ?>>→</a>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <?php
                $title = 'В очереди пока нет заявок';
                $description = 'Назначенные вам обращения появятся здесь. Список учитывает только вашу рабочую очередь.';
                $actionLabel = '';
                $actionHref = '';
                require dirname(__DIR__, 3) . '/components/empty-state.php';
                ?>
            <?php endif; ?>
        </section>
        <p class="workspace-footnote">Приоритет и допустимые переходы статусов устанавливаются backend. Необновлённая карточка не даёт права выполнять действие, которого нет в рабочем процессе.</p>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
