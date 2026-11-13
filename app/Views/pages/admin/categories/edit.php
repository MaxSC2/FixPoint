<?php
declare(strict_types=1);

$category = isset($category) && is_array($category) ? $category : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];
$categoryId = (string) ($category['id'] ?? '');
$name = (string) ($old['name'] ?? $category['name'] ?? '');
$slug = (string) ($old['slug'] ?? $category['slug'] ?? '');
$description = (string) ($old['description'] ?? $category['description'] ?? '');
$sortOrder = (string) ($old['sort_order'] ?? $category['sort_order'] ?? '0');
$isActive = array_key_exists('is_active', $old) ? !empty($old['is_active']) : !empty($category['is_active']);
$pageTitle = 'Редактировать категорию';
$activeNav = 'admin';
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div><nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/admin">Панель администратора</a><span aria-hidden="true">/</span><a href="/admin/categories">Категории</a><span aria-hidden="true">/</span><span aria-current="page">Редактирование</span></nav><span class="eyebrow">Справочник / FixPoint</span><h1>Редактировать категорию</h1><p><?= e($name) ?></p></div>
        </div>
        <nav class="workspace-nav" aria-label="Разделы администратора" data-reveal><a class="workspace-nav__link" href="/admin">Обзор</a><a class="workspace-nav__link" href="/admin/requests">Заявки</a><a class="workspace-nav__link" href="/admin/users">Пользователи</a><a class="workspace-nav__link" href="/admin/services">Услуги</a><a class="workspace-nav__link is-active" href="/admin/categories" aria-current="page">Категории</a></nav>

        <div class="admin-edit-layout">
            <section class="request-form-panel" data-reveal>
                <div class="request-form-panel__head"><span class="eyebrow">Параметры категории</span><h2><?= e($name !== '' ? $name : 'Категория') ?></h2><p>Изменения названия и slug отражаются в каталоге услуг и его фильтрах.</p></div>
                <?php if (isset($errors['general'])): ?><div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div><?php endif; ?>
                <form class="admin-record-form" method="post" action="/admin/categories/<?= e(rawurlencode($categoryId)) ?>/edit">
                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                    <label class="field<?= isset($errors['name']) ? ' field--invalid' : '' ?>" for="edit-category-name"><span class="field__label">Название</span><input id="edit-category-name" type="text" name="name" value="<?= e($name) ?>" maxlength="120" required><?php if (isset($errors['name'])): ?><small class="field__error"><?= e((string) $errors['name']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['slug']) ? ' field--invalid' : '' ?>" for="edit-category-slug"><span class="field__label">Slug</span><input id="edit-category-slug" type="text" name="slug" value="<?= e($slug) ?>" maxlength="140" pattern="[a-z0-9-]+" required><?php if (isset($errors['slug'])): ?><small class="field__error"><?= e((string) $errors['slug']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['description']) ? ' field--invalid' : '' ?>" for="edit-category-description"><span class="field__label">Описание</span><textarea id="edit-category-description" name="description" rows="4" maxlength="2000"><?= e($description) ?></textarea><?php if (isset($errors['description'])): ?><small class="field__error"><?= e((string) $errors['description']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['sort_order']) ? ' field--invalid' : '' ?>" for="edit-category-order"><span class="field__label">Порядок отображения</span><input id="edit-category-order" type="number" name="sort_order" min="0" max="100000" step="1" value="<?= e($sortOrder) ?>"><?php if (isset($errors['sort_order'])): ?><small class="field__error"><?= e((string) $errors['sort_order']) ?></small><?php endif; ?></label>
                    <div class="admin-toggle-row"><div><strong>Категория активна</strong><small>Неактивная категория и её услуги не отображаются в публичном каталоге.</small><?php if (isset($errors['is_active'])): ?><small class="field__error"><?= e((string) $errors['is_active']) ?></small><?php endif; ?></div><span><input type="hidden" name="is_active" value="0"><input id="edit-category-active" type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>><label for="edit-category-active" class="sr-only">Активная категория</label></span></div>
                    <div class="request-form__actions"><a class="button button--secondary" href="/admin/categories">Отмена</a><button class="button button--primary button--large" type="submit">Сохранить категорию</button></div>
                </form>
            </section>
            <aside class="request-create-aside" data-reveal><span class="request-create-aside__mark" aria-hidden="true">i</span><span class="eyebrow eyebrow--light">Важно для каталога</span><h2>Структура влияет на поиск.</h2><ol><li><span>01</span><div><strong>Название</strong><p>Понятно объясняет направление клиенту.</p></div></li><li><span>02</span><div><strong>Slug</strong><p>Используется в ссылках и фильтрах каталога.</p></div></li><li><span>03</span><div><strong>Порядок</strong><p>Определяет последовательность категорий.</p></div></li></ol></aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
