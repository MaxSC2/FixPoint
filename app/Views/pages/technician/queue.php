<?php
declare(strict_types=1);

/**
 * @var array{id: int, full_name: string, email: string, role: string} $authUser
 * @var list<array<string, mixed>> $requests
 * @var array{total: int, active: int, awaiting_approval: int, completed: int} $summary
 */
$pageTitle = 'Мои заявки';
require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="page-hero page-hero--compact">
    <div class="container">
        <span class="eyebrow"><span class="eyebrow__dot"></span> Рабочее место мастера</span>
        <h1>Мои заявки</h1>
        <p>Назначенные вам заявки. Очередь ограничена вашими заявками и проверяется на сервере.</p>
    </div>
</section>

<section class="workspace-section">
    <div class="container">
        <div class="stat-grid">
            <article class="stat-card">
                <span class="stat-card__label">Всего назначено</span>
                <span class="stat-card__value"><?= (int) $summary['total'] ?></span>
                <span class="stat-card__note">За всё время</span>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">В работе</span>
                <span class="stat-card__value"><?= (int) $summary['active'] ?></span>
                <span class="stat-card__note">Требуют действий</span>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Ждут клиента</span>
                <span class="stat-card__value"><?= (int) $summary['awaiting_approval'] ?></span>
                <span class="stat-card__note">Смета на согласовании</span>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Завершено</span>
                <span class="stat-card__value"><?= (int) $summary['completed'] ?></span>
                <span class="stat-card__note">Ремонт выполнен</span>
            </article>
        </div>

        <div class="panel panel--spaced">
            <div class="panel__head">
                <div>
                    <h2>Очередь</h2>
                    <p>Сначала показаны заявки, которые ещё требуют работы.</p>
                </div>
            </div>

            <?php if ($requests === []): ?>
                <?php
                $title = 'Назначенных заявок нет';
                $description = 'Когда администратор назначит вам заявку, она появится в этом списке.';
                require dirname(__DIR__, 2) . '/components/empty-state.php';
                ?>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Заявка</th>
                                <th scope="col">Клиент</th>
                                <th scope="col">Устройство</th>
                                <th scope="col">Статус</th>
                                <th scope="col">Обновлена</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <span class="data-table__primary"><?= e($request['public_number']) ?></span>
                                    <span class="data-table__meta"><?= e($request['service_name'] ?? 'Услуга уточняется') ?></span>
                                </td>
                                <td><?= e($request['customer_name']) ?></td>
                                <td>
                                    <?= e($request['device_type']) ?>
                                    <span class="data-table__meta">
                                        <?= e(trim((string) $request['device_brand'] . ' ' . (string) $request['device_model'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status = (string) $request['status'];
                                    require dirname(__DIR__, 2) . '/components/status-badge.php';
                                    ?>
                                </td>
                                <td><?= e(format_datetime((string) $request['updated_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>