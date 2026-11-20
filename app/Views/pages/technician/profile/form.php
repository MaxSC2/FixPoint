<?php
declare(strict_types=1);

$pageTitle = 'Мой профиль мастера';
$activeNav = 'technician';
$profile = isset($profile) && is_array($profile) ? $profile : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$selected = isset($old['specialties']) && is_array($old['specialties'])
    ? array_map('intval', $old['specialties'])
    : (isset($profile['specialty_ids']) && is_array($profile['specialty_ids']) ? $profile['specialty_ids'] : []);
$value = static fn (string $key, string $default = ''): string => (string) ($old[$key] ?? $profile[$key] ?? $default);
$isPublic = $value('is_public', (string) ($profile['is_public'] ?? 1)) !== '0';
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact"><div><span class="eyebrow">Кабинет мастера / Профиль</span><h1>Мой публичный профиль</h1><p>Расскажите клиентам о специализации и направлениях работы. Контактные данные здесь не публикуются.</p></div><a class="button button--ghost" href="/masters/<?= e((string) ($profile['technician_id'] ?? '')) ?>">Посмотреть профиль</a></div>
        <nav class="workspace-nav" aria-label="Разделы мастера"><a class="workspace-nav__link" href="/technician/requests">Рабочая очередь</a><a class="workspace-nav__link is-active" href="/technician/profile" aria-current="page">Мой профиль</a></nav>
        <section class="request-form-panel">
            <form class="request-form" method="post" action="/technician/profile" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                <div class="technician-avatar-editor">
                    <?php if (!empty($profile['avatar_storage_key']) && (int) ($profile['is_public'] ?? 0) === 1 && (int) ($profile['admin_is_visible'] ?? 1) === 1): ?>
                        <img class="technician-avatar-editor__preview" src="/masters/<?= e((string) $profile['technician_id']) ?>/avatar" alt="Текущий аватар">
                    <?php endif; ?>
                    <label class="field" for="profile-avatar">
                        <span class="field__label">Фотография профиля</span>
                        <input id="profile-avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                        <small class="field__help">JPEG, PNG или WebP. До 8 МБ, не более одного изображения. Файл будет храниться приватно и показываться только вместе с публичным профилем.</small>
                        <?php if (isset($errors['avatar'])): ?><small class="field__error"><?= e($errors['avatar']) ?></small><?php endif; ?>
                    </label>
                </div>
                <label class="field" for="profile-headline"><span class="field__label">Заголовок профиля</span><input id="profile-headline" name="headline" maxlength="140" value="<?= e($value('headline')) ?>" placeholder="Например, ремонт ноутбуков и диагностика ПК"><?php if (isset($errors['headline'])): ?><small class="field__error"><?= e($errors['headline']) ?></small><?php endif; ?></label>
                <label class="field" for="profile-bio"><span class="field__label">О себе</span><textarea id="profile-bio" name="bio" rows="5" maxlength="2000" placeholder="Опишите, с какими устройствами работаете и какие задачи выполняете."><?= e($value('bio')) ?></textarea><small class="field__help">До 2000 символов. Не указывайте телефон, почту, пароли или данные клиентов.</small><?php if (isset($errors['bio'])): ?><small class="field__error"><?= e($errors['bio']) ?></small><?php endif; ?></label>
                <fieldset class="master-specialty-picker">
                    <legend>Специализации</legend>
                    <p>Выберите направления из действующего каталога услуг.</p>
                    <div class="master-specialty-picker__grid">
                        <?php foreach ($categories as $category): ?>
                            <label><input type="checkbox" name="specialties[]" value="<?= e((string) $category['id']) ?>" <?= in_array((int) $category['id'], $selected, true) ? 'checked' : '' ?>><span><?= e($category['name']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors['specialties'])): ?><small class="field__error"><?= e($errors['specialties']) ?></small><?php endif; ?>
                </fieldset>
                <label class="checkbox-field"><input type="checkbox" name="is_public" value="1" <?= $isPublic ? 'checked' : '' ?>><span>Показывать профиль в публичном каталоге мастеров и доске почёта</span></label>
                <div class="request-form__actions"><button class="button button--primary button--large" type="submit">Сохранить профиль</button><a class="button button--ghost" href="/technician/requests">Назад</a></div>
            </form>
        </section>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
