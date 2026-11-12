<?php
declare(strict_types=1);

$user = isset($user) && is_array($user) ? $user : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];
$userId = (string) ($user['id'] ?? '');
$userName = (string) ($old['full_name'] ?? $user['full_name'] ?? '');
$email = (string) ($old['email'] ?? $user['email'] ?? '');
$phone = (string) ($old['phone'] ?? $user['phone'] ?? '');
$selectedRole = (string) ($old['role'] ?? $user['role'] ?? 'customer');
$isActive = array_key_exists('is_active', $old) ? !empty($old['is_active']) : !empty($user['is_active']);
$roles = ['customer' => 'Клиент', 'technician' => 'Мастер', 'admin' => 'Администратор'];
$pageTitle = 'Редактирование пользователя';
$activeNav = 'admin';
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div><nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/admin">Панель администратора</a><span aria-hidden="true">/</span><a href="/admin/users">Пользователи</a><span aria-hidden="true">/</span><span aria-current="page">Редактирование</span></nav><span class="eyebrow">Учётная запись / FixPoint</span><h1>Редактировать пользователя</h1><p><?= e($email) ?></p></div>
        </div>
        <nav class="workspace-nav" aria-label="Разделы администратора" data-reveal><a class="workspace-nav__link" href="/admin">Обзор</a><a class="workspace-nav__link" href="/admin/requests">Заявки</a><a class="workspace-nav__link is-active" href="/admin/users" aria-current="page">Пользователи</a><a class="workspace-nav__link" href="/admin/services">Услуги</a><a class="workspace-nav__link" href="/admin/categories">Категории</a></nav>

        <div class="admin-edit-layout">
            <section class="request-form-panel" data-reveal>
                <div class="request-form-panel__head"><span class="eyebrow">Параметры доступа</span><h2><?= e($userName !== '' ? $userName : 'Пользователь') ?></h2><p>Изменения роли и активности влияют на доступ к разделам системы.</p></div>
                <?php if (isset($errors['general'])): ?><div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div><?php endif; ?>
                <form class="admin-record-form" method="post" action="/admin/users/<?= e(rawurlencode($userId)) ?>/edit">
                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                    <label class="field<?= isset($errors['full_name']) ? ' field--invalid' : '' ?>" for="edit-user-name"><span class="field__label">Имя и фамилия</span><input id="edit-user-name" type="text" name="full_name" value="<?= e($userName) ?>" maxlength="160" required><?php if (isset($errors['full_name'])): ?><small class="field__error"><?= e((string) $errors['full_name']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['email']) ? ' field--invalid' : '' ?>" for="edit-user-email"><span class="field__label">Электронная почта</span><input id="edit-user-email" type="email" name="email" value="<?= e($email) ?>" maxlength="190" required><?php if (isset($errors['email'])): ?><small class="field__error"><?= e((string) $errors['email']) ?></small><?php endif; ?></label>
                    <label class="field" for="edit-user-phone"><span class="field__label">Телефон</span><input id="edit-user-phone" type="tel" name="phone" value="<?= e($phone) ?>" maxlength="32"></label>
                    <label class="field<?= isset($errors['role']) ? ' field--invalid' : '' ?>" for="edit-user-role"><span class="field__label">Роль</span><select id="edit-user-role" name="role" required><?php foreach ($roles as $value => $label): ?><option value="<?= e($value) ?>" <?= $selectedRole === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if (isset($errors['role'])): ?><small class="field__error"><?= e((string) $errors['role']) ?></small><?php endif; ?></label>
                    <div class="admin-toggle-row"><div><strong>Учётная запись активна</strong><small>Отключённый пользователь не сможет войти в систему.</small></div><span><input type="hidden" name="is_active" value="0"><input id="edit-user-active" type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>><label for="edit-user-active" class="sr-only">Активная учётная запись</label></span></div>
                    <div class="request-form__actions"><a class="button button--secondary" href="/admin/users">Отмена</a><button class="button button--primary button--large" type="submit">Сохранить изменения</button></div>
                </form>
            </section>
            <aside class="request-create-aside" data-reveal><span class="request-create-aside__mark" aria-hidden="true">i</span><span class="eyebrow eyebrow--light">Безопасность</span><h2>Роль — это граница доступа.</h2><ol><li><span>01</span><div><strong>Клиент</strong><p>Работает только со своими заявками.</p></div></li><li><span>02</span><div><strong>Мастер</strong><p>Работает с назначенными обращениями.</p></div></li><li><span>03</span><div><strong>Администратор</strong><p>Управляет системой и распределением работ.</p></div></li></ol></aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
