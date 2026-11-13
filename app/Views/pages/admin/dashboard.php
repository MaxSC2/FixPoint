<?php
declare(strict_types=1);

/**
 * @var array<string, mixed> $authUser
 * @var list<array<string, mixed>> $requests
 * @var array<string, int> $statusCounts
 * @var array<string, int> $summary
 */
$pageTitle = 'Администрирование';
require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="page-hero page-hero--compact">
    <div class="container">
        <span class="eyebrow"><span class="eyebrow__dot"></span> Администрирование</span>
        <h1>Обзор сервисного центра</h1>
        <p>Сводные показатели по заявкам, пользователям и каталогу. Все значения рассчитаны по данным MySQL.</p>
    </div>
</section>

<section class="workspace-section">
    <div class="container">
        <div class="stat-grid">
            <article class="stat-card">
                <span class="stat-card__label">Всего заявок</span>
                <span class="stat-card__value"><?= (int) $summary['requests_total'] ?></span>
                <span class="stat-card__note">За всё время</span>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">В работе</span>
                <span class="stat-card__value"><?= (int) $summary['requests_active'] ?></span>
                <span class="stat-card__note">Не завершены и не закрыты</span>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Без мастера</span>
                <span class="stat-card__value"><?= (int) $summary['requests_unassigned'] ?></span>
                <span class="stat-card__note">Требуют назначения</span>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Завершено</span>
                <span class="stat-card__value"><?= (int) $summary['requests_completed'] ?></span>
                <span class="stat-card__note">Ремонт выполнен</span>
            </article>
        </div>

        <div class="workspace-columns">
            <div class="panel panel--spaced">
                <div class="panel__head">
                    <div>
                        <h2>Последние заявки</h2>
                        <p>Новые и недавно обновлённые обращения.</p>
                    </div>
                </div>

                <?php if ($requests === []): ?>
                    <?php
                    $title = 'Заявок пока нет';
                    $description = 'После первой заявки здесь появится оперативная сводка.';
                    require dirname(__DIR__, 2) . '/components/empty-state.php';
                    ?>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Заявка</th>
                                    <th scope="col">Клиент</th>
                                    <th scope="col">Мастер</th>
                                    <th scope="col">Статус</th>
                                    <th scope="col">Создана</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td>
                                        <span class="data-table__primary"><?= e($request['public_number']) ?></span>
                                        <span class="data-table__meta"><?= e($request['device_type']) ?></span>
                                    </td>
                                    <td><?= e($request['customer_name']) ?></td>
                                    <td><?= e($request['technician_name'] ?? 'Не назначен') ?></td>
                                    <td>
                                        <?php
                                        $status = (string) $request['status'];
                                        require dirname(__DIR__, 2) . '/components/status-badge.php';
                                        ?>
                                    </td>
                                    <td><?= e(format_datetime((string) $request['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="panel panel--spaced">
                <div class="panel__head">
                    <div>
                        <h2>Справочники</h2>
                        <p>Активные записи системы.</p>
                    </div>
                </div>
                <dl class="summary-list">
                    <div>
                        <dt>Пользователи</dt>
                        <dd><?= (int) $summary['users_total'] ?></dd>
                    </div>
                    <div>
                        <dt>Клиенты</dt>
                        <dd><?= (int) $summary['customers'] ?></dd>
                    </div>
                    <div>
                        <dt>Мастера</dt>
                        <dd><?= (int) $summary['technicians'] ?></dd>
                    </div>
                    <div>
                        <dt>Активные услуги</dt>
                        <dd><?= (int) $summary['services_active'] ?></dd>
                    </div>
                    <div>
                        <dt>Активные категории</dt>
                        <dd><?= (int) $summary['categories_active'] ?></dd>
                    </div>
                </dl>
            </aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>