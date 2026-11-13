<?php
declare(strict_types=1);

$pageTitle = 'Пользователи';
$activeNav = 'admin';
$users = isset($users) && is_array($users) ? array_values(array_filter($users, 'is_array')) : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];
$roles = [
    'customer' => 'Клиент',
    'technician' => 'Мастер',
    'admin' => 'Администратор',
];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div><span class="eyebrow">Администрирование / Учётные записи</span><h1>Пользователи</h1><p>Создание учётных записей, назначение ролей и управление доступностью.</p></div>
            <span class="admin-record-count"><strong><?= e((string) count($users)) ?></strong><small>в текущем списке</small></span>
        </div>
        <nav class="workspace-nav" aria-label="Разделы администратора" data-reveal>
            <a class="workspace-nav__link" href="/admin">Обзор</a><a class="workspace-nav__link" href="/admin/requests">Заявки</a><a class="workspace-nav__link is-active" href="/admin/users" aria-current="page">Пользователи</a><a class="workspace-nav__link" href="/admin/services">Услуги</a><a class="workspace-nav__link" href="/admin/categories">Категории</a>
        </nav>

        <div class="admin-crud-layout">
            <section class="staff-list-panel" data-reveal>
                <div class="request-list-panel__head"><div><span class="eyebrow">Учётные записи</span><h2>Список пользователей</h2></div></div>
                <?php if ($users !== []): ?>
                    <div class="admin-request-table-wrap">
                        <table class="admin-request-table">
                            <thead><tr><th>Пользователь</th><th>Контакты</th><th>Роль</th><th>Состояние</th><th><span class="sr-only">Редактировать</span></th></tr></thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $userId = (string) ($user['id'] ?? '');
                                    $role = (string) ($user['role'] ?? '');
                                    $roleLabel = $roles[$role] ?? 'Неизвестная роль';
                                    $isActive = !empty($user['is_active']);
                                    ?>
                                    <tr>
                                        <td><span class="admin-request-primary"><?= e((string) ($user['full_name'] ?? 'Без имени')) ?><small><?= e((string) ($user['email'] ?? '')) ?></small></span></td>
                                        <td><?= e((string) ($user['phone'] ?? 'Не указан')) ?></td>
                                        <td><?= e($roleLabel) ?></td>
                                        <td><span class="admin-state-chip <?= $isActive ? 'is-active' : 'is-inactive' ?>"><?= $isActive ? 'Активен' : 'Отключён' ?></span></td>
                                        <td><a class="admin-row-open" href="/admin/users/<?= e(rawurlencode($userId)) ?>/edit" aria-label="Редактировать <?= e((string) ($user['full_name'] ?? 'пользователя')) ?>">↗</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <?php $title = 'Пользователей пока нет'; $description = 'Создайте первую учётную запись. Список не заполняется демонстрационными данными.'; $actionLabel = ''; $actionHref = ''; require dirname(__DIR__, 3) . '/components/empty-state.php'; ?>
                <?php endif; ?>
            </section>

            <aside class="admin-create-panel" data-reveal>
                <span class="eyebrow">Новая запись</span><h2>Создать пользователя</h2><p>Роль определяет доступ к рабочему пространству. Создавайте права только по необходимости.</p>
                <?php if (isset($errors['general'])): ?><div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div><?php endif; ?>
                <form class="admin-record-form" method="post" action="/admin/users">
                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                    <label class="field<?= isset($errors['full_name']) ? ' field--invalid' : '' ?>" for="admin-user-name"><span class="field__label">Имя и фамилия</span><input id="admin-user-name" type="text" name="full_name" value="<?= e((string) ($old['full_name'] ?? '')) ?>" maxlength="160" required><?php if (isset($errors['full_name'])): ?><small class="field__error"><?= e((string) $errors['full_name']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['email']) ? ' field--invalid' : '' ?>" for="admin-user-email"><span class="field__label">Электронная почта</span><input id="admin-user-email" type="email" name="email" value="<?= e((string) ($old['email'] ?? '')) ?>" maxlength="190" required><?php if (isset($errors['email'])): ?><small class="field__error"><?= e((string) $errors['email']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['phone']) ? ' field--invalid' : '' ?>" for="admin-user-phone"><span class="field__label">Телефон</span><input id="admin-user-phone" type="tel" name="phone" value="<?= e((string) ($old['phone'] ?? '')) ?>" maxlength="32"><?php if (isset($errors['phone'])): ?><small class="field__error"><?= e((string) $errors['phone']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['role']) ? ' field--invalid' : '' ?>" for="admin-user-role"><span class="field__label">Роль</span><select id="admin-user-role" name="role" required><?php foreach ($roles as $value => $label): ?><option value="<?= e($value) ?>" <?= (string) ($old['role'] ?? 'customer') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if (isset($errors['role'])): ?><small class="field__error"><?= e((string) $errors['role']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['password']) ? ' field--invalid' : '' ?>" for="admin-user-password"><span class="field__label">Временный пароль</span><input id="admin-user-password" type="password" name="password" autocomplete="new-password" required><?php if (isset($errors['password'])): ?><small class="field__error"><?= e((string) $errors['password']) ?></small><?php else: ?><small class="field__help">Пароль не сохраняется в старых значениях формы.</small><?php endif; ?></label>
                    <button class="button button--primary button--large" type="submit">Создать учётную запись</button>
                </form>
            </aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
