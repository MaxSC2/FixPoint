<?php
declare(strict_types=1);

$request = isset($request) && is_array($request) ? $request : [];
$technicians = isset($technicians) && is_array($technicians) ? array_values(array_filter($technicians, 'is_array')) : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];
$requestId = (string) ($request['id'] ?? '');
$publicNumber = (string) ($request['public_number'] ?? 'Заявка');
$deviceTitle = trim(implode(' ', array_filter([
    (string) ($request['device_brand'] ?? ''),
    (string) ($request['device_model'] ?? ''),
])));
if ($deviceTitle === '') {
    $deviceTitle = (string) ($request['device_type'] ?? 'Устройство');
}
$currentTechnician = isset($request['technician']) && is_array($request['technician']) ? $request['technician'] : null;
$currentTechnicianId = (string) ($currentTechnician['id'] ?? '');
$selectedTechnicianId = (string) ($old['technician_id'] ?? $currentTechnicianId);
$pageTitle = 'Назначение мастера · ' . $publicNumber;
$activeNav = 'admin';
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div>
                <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/admin">Панель администратора</a><span aria-hidden="true">/</span><a href="/admin/requests">Заявки</a><span aria-hidden="true">/</span><span aria-current="page">Назначение мастера</span></nav>
                <span class="eyebrow">Распределение работ / FixPoint</span>
                <h1>Назначить мастера</h1>
                <p><?= e($publicNumber) ?> · <?= e($deviceTitle) ?></p>
            </div>
        </div>

        <nav class="workspace-nav" aria-label="Разделы администратора" data-reveal>
            <a class="workspace-nav__link" href="/admin">Обзор</a>
            <a class="workspace-nav__link is-active" href="/admin/requests" aria-current="page">Заявки</a>
            <a class="workspace-nav__link" href="/admin/users">Пользователи</a>
            <a class="workspace-nav__link" href="/admin/services">Услуги</a>
            <a class="workspace-nav__link" href="/admin/categories">Категории</a>
        </nav>

        <div class="admin-assign-layout">
            <section class="request-form-panel" data-reveal>
                <div class="request-form-panel__head"><span class="eyebrow">Назначение исполнителя</span><h2>Кто займётся заявкой?</h2><p>В списке отображаются активные пользователи с ролью мастера. Назначение можно изменить позже.</p></div>
                <?php if (isset($errors['general'])): ?><div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div><?php endif; ?>
                <form class="admin-assign-form" method="post" action="/admin/requests/<?= e(rawurlencode($requestId)) ?>/assign">
                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                    <label class="field<?= isset($errors['technician_id']) ? ' field--invalid' : '' ?>" for="assign-technician">
                        <span class="field__label">Мастер</span>
                        <select id="assign-technician" name="technician_id" required <?= isset($errors['technician_id']) ? 'aria-invalid="true" aria-describedby="assign-technician-error"' : '' ?>>
                            <option value="">Выберите мастера</option>
                            <?php foreach ($technicians as $technician): ?>
                                <?php
                                $technicianId = (string) ($technician['id'] ?? '');
                                $technicianName = (string) ($technician['full_name'] ?? '');
                                if ($technicianId === '' || $technicianName === '') { continue; }
                                ?>
                                <option value="<?= e($technicianId) ?>" <?= $selectedTechnicianId === $technicianId ? 'selected' : '' ?>><?= e($technicianName) ?><?= !empty($technician['email']) ? ' · ' . e((string) $technician['email']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['technician_id'])): ?><small class="field__error" id="assign-technician-error"><?= e((string) $errors['technician_id']) ?></small><?php endif; ?>
                        <?php if ($technicians === []): ?><small class="field__help">Нет доступных активных мастеров. Проверьте роли и активность пользователей.</small><?php endif; ?>
                    </label>
                    <div class="request-form__actions"><a class="button button--secondary" href="/admin/requests">Отмена</a><button class="button button--primary button--large" type="submit">Сохранить назначение</button></div>
                </form>
            </section>

            <aside class="request-create-aside" data-reveal>
                <span class="request-create-aside__mark" aria-hidden="true">i</span>
                <span class="eyebrow eyebrow--light">Текущая заявка</span>
                <h2><?= e($publicNumber) ?></h2>
                <ol>
                    <li><span>01</span><div><strong>Устройство</strong><p><?= e($deviceTitle) ?></p></div></li>
                    <li><span>02</span><div><strong>Клиент</strong><p><?= e((string) ($request['customer']['full_name'] ?? 'Не указан')) ?></p></div></li>
                    <li><span>03</span><div><strong>Текущий мастер</strong><p><?= e((string) ($currentTechnician['full_name'] ?? 'Пока не назначен')) ?></p></div></li>
                </ol>
            </aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
