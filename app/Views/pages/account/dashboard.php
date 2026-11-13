<?php
declare(strict_types=1);

/**
 * @var array{id: int, full_name: string, email: string, role: string} $authUser
 * @var list<array<string, mixed>> $requests
 * @var array<string, int> $statusCounts
 * @var array{total: int, active: int, awaiting_approval: int, completed: int} $summary
 */
$pageTitle = 'Личный кабинет';
require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="page-hero page-hero--compact">
    <div class="container">
        <span class="eyebrow"><span class="eyebrow__dot"></span> Личный кабинет</span>
        <h1>Здравствуйте, <?= e($authUser['full_name']) ?></h1>
        <p>Здесь собраны ваши заявки и их текущее состояние. Данные видны только вам.</p>
    </div>
</section>

<section class="workspace-section">
    <div class="container">
        <div class="stat-grid">
            <article class="stat-card">
                <span class="stat-card__label">Всего заявок</span>
                <span class="stat-card__value"><?= (int) $summary['total'] ?></span>
                <span class="stat-card__note">За всё время</span>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">В работе</span>
                <span class="stat-card__value"><?= (int) $summary['active'] ?></span>
                <span class="stat-card__note">От принятой до выдачи</span>
            </article>
            <article class="stat-card">
                <span class="stat-card__label">Ждут вашего решения</span>
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
                    <h2>Последние заявки</h2>
                    <p>Новые заявки появятся здесь сразу после оформления.</p>
                </div>
            </div>

            <?php if ($requests === []): ?>
                <?php
                $title = 'Заявок пока нет';
                $description = 'Когда вы оформите заявку на ремонт, она появится в этом списке.';
                require dirname(__DIR__, 2) . '/components/empty-state.php';
                ?>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Заявка</th>
                                <th scope="col">Устройство</th>
                                <th scope="col">Услуга</th>
                                <th scope="col">Статус</th>
                                <th scope="col">Обновлена</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <span class="data-table__primary"><?= e($request['public_number']) ?></span>
                                    <span class="data-table__meta">Создана <?= e(format_datetime((string) $request['created_at'])) ?></span>
                                </td>
                                <td>
                                    <?= e($request['device_type']) ?>
                                    <?php if ($request['device_brand'] !== null || $request['device_model'] !== null): ?>
                                        <span class="data-table__meta">
                                            <?= e(trim((string) $request['device_brand'] . ' ' . (string) $request['device_model'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($request['service_name'] ?? 'Услуга уточняется') ?></td>
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