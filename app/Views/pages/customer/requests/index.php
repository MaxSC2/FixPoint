<?php
declare(strict_types=1);

$pageTitle = 'Мои заявки';
$activeNav = 'requests';
$requestList = isset($requestList) && is_array($requestList) ? $requestList : [];
$items = isset($requestList['items']) && is_array($requestList['items']) ? $requestList['items'] : [];
$items = array_values(array_filter($items, 'is_array'));
$pagination = isset($requestList['pagination']) && is_array($requestList['pagination']) ? $requestList['pagination'] : [];
$filters = isset($requestList['filters']) && is_array($requestList['filters']) ? $requestList['filters'] : [];
$query = (string) ($filters['q'] ?? '');
$selectedStatus = (string) ($filters['status'] ?? '');
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
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div>
                <span class="eyebrow">Клиентское пространство / FixPoint</span>
                <h1>Мои заявки</h1>
                <p>Все обращения в одном месте: от первичной диагностики до завершения работ.</p>
            </div>
            <a class="button button--primary button--large" href="/requests/create">Новая заявка <span aria-hidden="true">↗</span></a>
        </div>

        <nav class="workspace-nav" aria-label="Разделы личного кабинета" data-reveal>
            <a class="workspace-nav__link" href="/account">Обзор</a>
            <a class="workspace-nav__link is-active" href="/requests" aria-current="page">Мои заявки</a>
            <a class="workspace-nav__link workspace-nav__link--action" href="/requests/create">Создать обращение <span aria-hidden="true">+</span></a>
        </nav>

        <div class="request-list-panel" data-reveal>
            <div class="request-list-panel__head">
                <div>
                    <span class="eyebrow">История обращений</span>
                    <h2>Список заявок</h2>
                </div>
                <span class="request-list-panel__count"><?= e((string) $totalRequests) ?> всего</span>
            </div>

            <form class="request-filters" method="get" action="/requests">
                <label class="field request-filters__search">
                    <span class="sr-only">Поиск по номеру или устройству</span>
                    <input type="search" name="q" value="<?= e($query) ?>" placeholder="Номер заявки или устройство">
                </label>
                <label class="field">
                    <span class="sr-only">Статус заявки</span>
                    <select name="status">
                        <option value="">Все статусы</option>
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span class="sr-only">Сортировка заявок</span>
                    <select name="sort">
                        <option value="newest" <?= $selectedSort === 'newest' ? 'selected' : '' ?>>Сначала новые</option>
                        <option value="oldest" <?= $selectedSort === 'oldest' ? 'selected' : '' ?>>Сначала старые</option>
                        <option value="updated" <?= $selectedSort === 'updated' ? 'selected' : '' ?>>Недавно обновлённые</option>
                    </select>
                </label>
                <button class="button button--primary" type="submit">Применить</button>
                <?php if ($query !== '' || $selectedStatus !== '' || $selectedSort !== 'newest'): ?>
                    <a class="button button--ghost" href="/requests">Сбросить</a>
                <?php endif; ?>
            </form>

            <?php if ($items !== []): ?>
                <div class="request-card-list">
                    <?php foreach ($items as $request): ?>
                        <?php require dirname(__DIR__, 3) . '/components/request-card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($lastPage > 1): ?>
                    <?php
                    $pageQuery = ['q' => $query, 'status' => $selectedStatus, 'sort' => $selectedSort];
                    $pageQuery = array_filter($pageQuery, static fn (string $value): bool => $value !== '');
                    $pageUrl = static fn (int $page): string => '/requests?' . http_build_query($pageQuery + ['page' => $page]);
                    ?>
                    <nav class="pagination request-pagination" aria-label="Страницы заявок">
                        <a class="pagination__link" href="<?= e($pageUrl(max(1, $currentPage - 1))) ?>" aria-label="Предыдущая страница" <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>←</a>
                        <span class="request-pagination__label">Страница <?= e((string) $currentPage) ?> из <?= e((string) $lastPage) ?></span>
                        <a class="pagination__link" href="<?= e($pageUrl(min($lastPage, $currentPage + 1))) ?>" aria-label="Следующая страница" <?= $currentPage >= $lastPage ? 'aria-disabled="true" tabindex="-1"' : '' ?>>→</a>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <?php
                $title = $query !== '' || $selectedStatus !== '' ? 'По этим условиям заявок нет' : 'Пока нет обращений';
                $description = $query !== '' || $selectedStatus !== ''
                    ? 'Измените фильтры или сбросьте поиск. Список показывает только ваши заявки.'
                    : 'Создайте первую заявку, чтобы зафиксировать устройство и описание проблемы. Дальнейшие этапы появятся здесь.';
                $actionLabel = $query !== '' || $selectedStatus !== '' ? 'Сбросить фильтры' : 'Создать заявку';
                $actionHref = $query !== '' || $selectedStatus !== '' ? '/requests' : '/requests/create';
                require dirname(__DIR__, 3) . '/components/empty-state.php';
                ?>
            <?php endif; ?>
        </div>
        <p class="workspace-footnote">Сведения и статусы отображаются по данным вашей заявки. Если мастер ещё не завершил диагностику, стоимость и сроки могут быть не определены.</p>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
