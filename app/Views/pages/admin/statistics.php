<?php
declare(strict_types=1);

$pageTitle = 'Статистика';
$activeNav = 'admin';
$summary = isset($summary) && is_array($summary) ? $summary : [];
$statusBreakdown = isset($statusBreakdown) && is_array($statusBreakdown) ? array_values(array_filter($statusBreakdown, 'is_array')) : [];
$monthlyRequests = isset($monthlyRequests) && is_array($monthlyRequests) ? array_values(array_filter($monthlyRequests, 'is_array')) : [];
$topServices = isset($topServices) && is_array($topServices) ? array_values(array_filter($topServices, 'is_array')) : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$from = (string) ($filters['from'] ?? '');
$to = (string) ($filters['to'] ?? '');
$totalRequests = max(0, (int) ($summary['total_requests'] ?? 0));
$completedRequests = max(0, (int) ($summary['requests_completed'] ?? 0));
$inProgressRequests = max(0, (int) ($summary['requests_in_progress'] ?? 0));
$waitingRequests = max(0, (int) ($summary['requests_waiting_approval'] ?? 0));
$rejectedRequests = max(0, (int) ($summary['requests_rejected'] ?? 0));
$completionRate = $totalRequests > 0 ? min(100, max(0, ($completedRequests / $totalRequests) * 100)) : 0.0;
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
$maxMonthlyCount = 0;
foreach ($monthlyRequests as $month) {
    $maxMonthlyCount = max($maxMonthlyCount, max(0, (int) ($month['request_count'] ?? 0)));
}
$maxServiceCount = 0;
foreach ($topServices as $service) {
    $maxServiceCount = max($maxServiceCount, max(0, (int) ($service['request_count'] ?? 0)));
}
require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div><span class="eyebrow">Аналитика / FixPoint</span><h1>Статистика сервиса</h1><p>Сводные показатели и распределение обращений за выбранный период.</p></div>
            <span class="admin-record-count"><strong><?= e(number_format($totalRequests, 0, ',', ' ')) ?></strong><small>заявок за период</small></span>
        </div>
        <nav class="workspace-nav" aria-label="Разделы администратора" data-reveal>
            <a class="workspace-nav__link" href="/admin">Обзор</a><a class="workspace-nav__link" href="/admin/requests">Заявки</a><a class="workspace-nav__link" href="/admin/users">Пользователи</a><a class="workspace-nav__link" href="/admin/services">Услуги</a><a class="workspace-nav__link" href="/admin/categories">Категории</a><a class="workspace-nav__link is-active" href="/admin/statistics" aria-current="page">Статистика</a>
        </nav>

        <form class="admin-period-filter" method="get" action="/admin/statistics" data-reveal>
            <div><span class="eyebrow">Период анализа</span><strong>Выберите даты для отчёта</strong></div>
            <label class="field" for="stats-from"><span class="field__label">С</span><input id="stats-from" type="date" name="from" value="<?= e($from) ?>"></label>
            <label class="field" for="stats-to"><span class="field__label">По</span><input id="stats-to" type="date" name="to" value="<?= e($to) ?>"></label>
            <button class="button button--primary" type="submit">Обновить</button>
            <?php if ($from !== '' || $to !== ''): ?><a class="button button--ghost" href="/admin/statistics">Сбросить</a><?php endif; ?>
        </form>

        <section class="admin-metrics admin-stat-metrics" aria-label="Сводные показатели" data-reveal>
            <article class="admin-metric admin-metric--blue"><span class="admin-metric__label">Всего заявок</span><strong><?= e(number_format($totalRequests, 0, ',', ' ')) ?></strong><small>За выбранный период</small></article>
            <article class="admin-metric admin-metric--green"><span class="admin-metric__label">Завершено</span><strong><?= e(number_format($completedRequests, 0, ',', ' ')) ?></strong><small><?= $totalRequests > 0 ? e(number_format($completionRate, 1, ',', ' ')) . '%' : 'Нет данных для расчёта' ?></small></article>
            <article class="admin-metric admin-metric--sand"><span class="admin-metric__label">В работе</span><strong><?= e(number_format($inProgressRequests, 0, ',', ' ')) ?></strong><small>Диагностика и ремонт</small></article>
            <article class="admin-metric admin-metric--amber"><span class="admin-metric__label">Ждут решения</span><strong><?= e(number_format($waitingRequests, 0, ',', ' ')) ?></strong><small>Согласование клиента</small></article>
        </section>

        <div class="admin-stat-grid">
            <section class="admin-stat-panel" data-reveal>
                <div class="admin-stat-panel__head"><div><span class="eyebrow">Состояние потока</span><h2>Распределение по статусам</h2></div></div>
                <?php if ($statusBreakdown !== []): ?>
                    <div class="admin-status-breakdown">
                        <?php foreach ($statusBreakdown as $item): ?>
                            <?php
                            $code = (string) ($item['status'] ?? '');
                            $label = $statusLabels[$code] ?? $code;
                            $count = max(0, (int) ($item['request_count'] ?? 0));
                            $percent = $totalRequests > 0 ? min(100, max(0, ($count / $totalRequests) * 100)) : 0.0;
                            ?>
                            <div class="admin-status-row">
                                <div class="admin-status-row__top"><span><?= e($label !== '' ? $label : 'Неизвестный статус') ?></span><strong><?= e(number_format($count, 0, ',', ' ')) ?></strong></div>
                                <div class="admin-status-row__track" role="img" aria-label="<?= e($label) ?>: <?= e((string) $count) ?> заявок"><span style="width: <?= e(number_format($percent, 1, '.', '')) ?>%"></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-stat-empty"><strong>Нет данных по статусам</strong><p>Распределение появится, когда в выбранном периоде будут зарегистрированы заявки.</p></div>
                <?php endif; ?>
            </section>

            <section class="admin-stat-panel" data-reveal>
                <div class="admin-stat-panel__head"><div><span class="eyebrow">Динамика</span><h2>Заявки по месяцам</h2></div></div>
                <?php if ($monthlyRequests !== [] && $maxMonthlyCount > 0): ?>
                    <div class="admin-month-chart" role="img" aria-label="Количество заявок по месяцам">
                        <?php foreach ($monthlyRequests as $month): ?>
                            <?php
                            $count = max(0, (int) ($month['request_count'] ?? 0));
                            $height = min(100, max(0, ($count / $maxMonthlyCount) * 100));
                            ?>
                            <div class="admin-month-chart__item">
                                <span class="admin-month-chart__value"><?= e((string) $count) ?></span>
                                <span class="admin-month-chart__bar"><i style="height: <?= e(number_format($height, 1, '.', '')) ?>%"></i></span>
                                <span class="admin-month-chart__label"><?= e((string) ($month['month_label'] ?? '—')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-stat-empty"><strong>Пока нет динамики</strong><p>График заполнится после появления заявок в нескольких периодах.</p></div>
                <?php endif; ?>
            </section>
        </div>

        <section class="admin-stat-panel admin-top-services" data-reveal>
            <div class="admin-stat-panel__head"><div><span class="eyebrow">Каталог</span><h2>Часто заказываемые услуги</h2></div></div>
            <?php if ($topServices !== []): ?>
                <div class="admin-service-ranking">
                    <?php foreach ($topServices as $index => $service): ?>
                        <?php $count = max(0, (int) ($service['request_count'] ?? 0)); $width = $maxServiceCount > 0 ? min(100, max(0, ($count / $maxServiceCount) * 100)) : 0.0; ?>
                        <div class="admin-service-ranking__row">
                            <span class="admin-service-ranking__rank"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                            <div class="admin-service-ranking__main"><strong><?= e((string) ($service['name'] ?? 'Услуга без названия')) ?></strong><span class="admin-service-ranking__track"><i style="width: <?= e(number_format($width, 1, '.', '')) ?>%"></i></span></div>
                            <b><?= e(number_format($count, 0, ',', ' ')) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-stat-empty"><strong>Недостаточно данных</strong><p>Популярность услуг будет рассчитываться по фактическим обращениям.</p></div>
            <?php endif; ?>
        </section>
        <p class="workspace-footnote">Показатели отражают агрегаты из базы данных за выбранный период. Процент завершения рассчитывается как отношение завершённых заявок к общему числу заявок в выборке.</p>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
