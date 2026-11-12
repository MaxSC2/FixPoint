<?php
declare(strict_types=1);

$pageTitle = 'Панель администратора';
$activeNav = 'admin';
$stats = isset($stats) && is_array($stats) ? $stats : [];
$recentRequests = isset($recentRequests) && is_array($recentRequests) ? array_values(array_filter($recentRequests, 'is_array')) : [];
$metricDefinitions = [
    'total_requests' => ['label' => 'Всего заявок', 'note' => 'Все обращения в системе', 'tone' => 'blue'],
    'requests_new' => ['label' => 'Новые', 'note' => 'Ожидают первичной обработки', 'tone' => 'sand'],
    'requests_in_progress' => ['label' => 'В работе', 'note' => 'Диагностика и ремонт', 'tone' => 'green'],
    'requests_waiting_approval' => ['label' => 'Ждут решения', 'note' => 'Нужно согласование клиента', 'tone' => 'amber'],
];
require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div>
                <span class="eyebrow">Операционная панель / FixPoint</span>
                <h1>Обзор сервиса</h1>
                <p>Заявки, сотрудники и справочники сервисного центра. Показатели формируются из базы данных.</p>
            </div>
            <a class="button button--primary button--large" href="/admin/requests">Открыть реестр <span aria-hidden="true">↗</span></a>
        </div>

        <nav class="workspace-nav" aria-label="Разделы администратора" data-reveal>
            <a class="workspace-nav__link is-active" href="/admin" aria-current="page">Обзор</a>
            <a class="workspace-nav__link" href="/admin/requests">Заявки</a>
            <a class="workspace-nav__link" href="/admin/users">Пользователи</a>
            <a class="workspace-nav__link" href="/admin/services">Услуги</a>
            <a class="workspace-nav__link" href="/admin/categories">Категории</a>
            <a class="workspace-nav__link workspace-nav__link--action" href="/admin/statistics">Статистика <span aria-hidden="true">↗</span></a>
        </nav>

        <section class="admin-metrics" aria-label="Показатели сервиса" data-reveal>
            <?php foreach ($metricDefinitions as $key => $definition): ?>
                <article class="admin-metric admin-metric--<?= e($definition['tone']) ?>">
                    <span class="admin-metric__label"><?= e($definition['label']) ?></span>
                    <strong><?= isset($stats[$key]) && is_numeric($stats[$key]) ? e(number_format((int) $stats[$key], 0, ',', ' ')) : '—' ?></strong>
                    <small><?= e($definition['note']) ?></small>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="admin-secondary-metrics" data-reveal>
            <div><span>Активные клиенты</span><strong><?= isset($stats['active_customers']) && is_numeric($stats['active_customers']) ? e(number_format((int) $stats['active_customers'], 0, ',', ' ')) : '—' ?></strong></div>
            <div><span>Активные мастера</span><strong><?= isset($stats['active_technicians']) && is_numeric($stats['active_technicians']) ? e(number_format((int) $stats['active_technicians'], 0, ',', ' ')) : '—' ?></strong></div>
            <div><span>Завершённые заявки</span><strong><?= isset($stats['requests_completed']) && is_numeric($stats['requests_completed']) ? e(number_format((int) $stats['requests_completed'], 0, ',', ' ')) : '—' ?></strong></div>
        </div>

        <section class="admin-recent" data-reveal>
            <div class="account-recent__head">
                <div><span class="eyebrow">Операционная работа</span><h2>Последние обращения</h2></div>
                <a class="text-link" href="/admin/requests">Весь реестр <span aria-hidden="true">↗</span></a>
            </div>
            <?php if ($recentRequests !== []): ?>
                <div class="request-card-list">
                    <?php foreach (array_slice($recentRequests, 0, 5) as $request): ?>
                        <?php require dirname(__DIR__, 3) . '/components/request-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php
                $title = 'Новых обращений пока нет';
                $description = 'Здесь появятся последние заявки из базы данных. Демонстрационные записи не подставляются.';
                $actionLabel = 'Открыть реестр';
                $actionHref = '/admin/requests';
                require dirname(__DIR__, 3) . '/components/empty-state.php';
                ?>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
