<?php
declare(strict_types=1);

$pageTitle = 'Реестр заявок';
$activeNav = 'admin';
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
$priorityLabels = ['low' => 'Низкий', 'normal' => 'Обычный', 'high' => 'Высокий', 'urgent' => 'Срочный'];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div>
                <span class="eyebrow">Администрирование / Реестр</span>
                <h1>Все заявки</h1>
                <p>Поиск по обращениям, контроль состояния и переход к назначению мастера.</p>
            </div>
            <span class="admin-record-count"><strong><?= e((string) $totalRequests) ?></strong><small>записей в выборке</small></span>
        </div>

        <nav class="workspace-nav" aria-label="Разделы администратора" data-reveal>
            <a class="workspace-nav__link" href="/admin">Обзор</a>
            <a class="workspace-nav__link is-active" href="/admin/requests" aria-current="page">Заявки</a>
            <a class="workspace-nav__link" href="/admin/users">Пользователи</a>
            <a class="workspace-nav__link" href="/admin/services">Услуги</a>
            <a class="workspace-nav__link" href="/admin/categories">Категории</a>
        </nav>

        <section class="staff-list-panel" data-reveal>
            <div class="request-list-panel__head"><div><span class="eyebrow">Реальные записи</span><h2>Фильтры реестра</h2></div><span class="request-list-panel__count"><?= e((string) $totalRequests) ?> всего</span></div>
            <form class="request-filters staff-filters admin-filters" method="get" action="/admin/requests">
                <label class="field request-filters__search"><span class="sr-only">Поиск заявки</span><input type="search" name="q" value="<?= e($query) ?>" placeholder="Номер, клиент или устройство"></label>
                <label class="field"><span class="sr-only">Статус заявки</span><select name="status"><option value="">Все статусы</option><?php foreach ($statusOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="sr-only">Приоритет</span><select name="priority"><option value="">Любой приоритет</option><?php foreach ($priorityLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $selectedPriority === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="sr-only">Сортировка</span><select name="sort"><option value="newest" <?= $selectedSort === 'newest' ? 'selected' : '' ?>>Сначала новые</option><option value="oldest" <?= $selectedSort === 'oldest' ? 'selected' : '' ?>>Сначала старые</option><option value="updated" <?= $selectedSort === 'updated' ? 'selected' : '' ?>>Недавно обновлённые</option></select></label>
                <button class="button button--primary" type="submit">Применить</button>
                <?php if ($query !== '' || $selectedStatus !== '' || $selectedPriority !== '' || $selectedSort !== 'newest'): ?><a class="button button--ghost" href="/admin/requests">Сбросить</a><?php endif; ?>
            </form>

            <?php if ($items !== []): ?>
                <div class="admin-request-table-wrap">
                    <table class="admin-request-table">
                        <thead><tr><th>Заявка / устройство</th><th>Клиент</th><th>Мастер</th><th>Статус</th><th>Приоритет</th><th>Создана</th><th><span class="sr-only">Открыть</span></th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $request): ?>
                                <?php
                                $requestId = (string) ($request['id'] ?? '');
                                $number = (string) ($request['public_number'] ?? 'Заявка');
                                $deviceType = (string) ($request['device_type'] ?? 'Устройство');
                                $deviceTitle = trim(implode(' ', array_filter([(string) ($request['device_brand'] ?? ''), (string) ($request['device_model'] ?? '')])));
                                if ($deviceTitle === '') { $deviceTitle = $deviceType; }
                                $customer = isset($request['customer']) && is_array($request['customer']) ? $request['customer'] : [];
                                $technician = isset($request['technician']) && is_array($request['technician']) ? $request['technician'] : [];
                                $priority = (string) ($request['priority'] ?? 'normal');
                                $dateLabel = '—';
                                if (!empty($request['created_at'])) {
                                    try { $dateLabel = (new DateTimeImmutable((string) $request['created_at']))->format('d.m.Y'); } catch (Throwable) { $dateLabel = 'Уточняется'; }
                                }
                                ?>
                                <tr>
                                    <td><a class="admin-request-primary" href="/requests/<?= e(rawurlencode($requestId)) ?>"><?= e($number) ?><small><?= e($deviceTitle) ?></small></a></td>
                                    <td><?= e((string) ($customer['full_name'] ?? '—')) ?></td>
                                    <td><?= e((string) ($technician['full_name'] ?? 'Не назначен')) ?></td>
                                    <td><?php $status = (string) ($request['status'] ?? ''); require dirname(__DIR__, 3) . '/components/status-badge.php'; ?></td>
                                    <td><span class="priority-chip priority-chip--<?= e(in_array($priority, ['low','normal','high','urgent'], true) ? $priority : 'normal') ?>"><?= e($priorityLabels[$priority] ?? 'Не указан') ?></span></td>
                                    <td><?= e($dateLabel) ?></td>
                                    <td><a class="admin-row-open" href="/requests/<?= e(rawurlencode($requestId)) ?>" aria-label="Открыть <?= e($number) ?>">↗</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($lastPage > 1): ?>
                    <?php
                    $pageQuery = ['q' => $query, 'status' => $selectedStatus, 'priority' => $selectedPriority, 'sort' => $selectedSort];
                    $pageQuery = array_filter($pageQuery, static fn (string $value): bool => $value !== '');
                    $pageUrl = static fn (int $page): string => '/admin/requests?' . http_build_query($pageQuery + ['page' => $page]);
                    ?>
                    <nav class="pagination request-pagination" aria-label="Страницы реестра"><a class="pagination__link" href="<?= e($pageUrl(max(1, $currentPage - 1))) ?>" <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>←</a><span class="request-pagination__label">Страница <?= e((string) $currentPage) ?> из <?= e((string) $lastPage) ?></span><a class="pagination__link" href="<?= e($pageUrl(min($lastPage, $currentPage + 1))) ?>" <?= $currentPage >= $lastPage ? 'aria-disabled="true" tabindex="-1"' : '' ?>>→</a></nav>
                <?php endif; ?>
            <?php else: ?>
                <?php
                $title = 'Заявки не найдены';
                $description = 'Измените параметры фильтрации. Реестр не подставляет демонстрационные записи.';
                $actionLabel = 'Сбросить фильтры';
                $actionHref = '/admin/requests';
                require dirname(__DIR__, 3) . '/components/empty-state.php';
                ?>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
