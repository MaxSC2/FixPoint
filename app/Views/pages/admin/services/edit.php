<?php
declare(strict_types=1);

$service = isset($service) && is_array($service) ? $service : [];
$categories = isset($categories) && is_array($categories) ? array_values(array_filter($categories, 'is_array')) : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];
$serviceId = (string) ($service['id'] ?? '');
$categoryId = (string) ($old['category_id'] ?? $service['category_id'] ?? '');
$name = (string) ($old['name'] ?? $service['name'] ?? '');
$slug = (string) ($old['slug'] ?? $service['slug'] ?? '');
$shortDescription = (string) ($old['short_description'] ?? $service['short_description'] ?? '');
$description = (string) ($old['description'] ?? $service['description'] ?? '');
$priceFrom = (string) ($old['price_from'] ?? $service['price_from'] ?? '');
$estimatedMinutes = (string) ($old['estimated_minutes'] ?? $service['estimated_minutes'] ?? '');
$isActive = array_key_exists('is_active', $old) ? !empty($old['is_active']) : !empty($service['is_active']);
$pageTitle = 'Редактировать услугу';
$activeNav = 'admin';
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div><nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/admin">Панель администратора</a><span aria-hidden="true">/</span><a href="/admin/services">Услуги</a><span aria-hidden="true">/</span><span aria-current="page">Редактирование</span></nav><span class="eyebrow">Публичный каталог / FixPoint</span><h1>Редактировать услугу</h1><p><?= e($name) ?></p></div>
        </div>
        <nav class="workspace-nav" aria-label="Разделы администратора" data-reveal><a class="workspace-nav__link" href="/admin">Обзор</a><a class="workspace-nav__link" href="/admin/requests">Заявки</a><a class="workspace-nav__link" href="/admin/users">Пользователи</a><a class="workspace-nav__link is-active" href="/admin/services" aria-current="page">Услуги</a><a class="workspace-nav__link" href="/admin/categories">Категории</a></nav>

        <div class="admin-edit-layout">
            <section class="request-form-panel" data-reveal>
                <div class="request-form-panel__head"><span class="eyebrow">Параметры услуги</span><h2><?= e($name !== '' ? $name : 'Услуга') ?></h2><p>Обновите карточку каталога. Не обещайте фиксированную цену там, где она зависит от диагностики.</p></div>
                <?php if (isset($errors['general'])): ?><div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div><?php endif; ?>
                <form class="admin-record-form" method="post" action="/admin/services/<?= e(rawurlencode($serviceId)) ?>/edit">
                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                    <label class="field<?= isset($errors['category_id']) ? ' field--invalid' : '' ?>" for="edit-service-category"><span class="field__label">Категория</span><select id="edit-service-category" name="category_id" required><?php foreach ($categories as $category): ?><option value="<?= e((string) ($category['id'] ?? '')) ?>" <?= $categoryId === (string) ($category['id'] ?? '') ? 'selected' : '' ?>><?= e((string) ($category['name'] ?? '')) ?></option><?php endforeach; ?></select><?php if (isset($errors['category_id'])): ?><small class="field__error"><?= e((string) $errors['category_id']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['name']) ? ' field--invalid' : '' ?>" for="edit-service-name"><span class="field__label">Название</span><input id="edit-service-name" type="text" name="name" value="<?= e($name) ?>" maxlength="160" required><?php if (isset($errors['name'])): ?><small class="field__error"><?= e((string) $errors['name']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['slug']) ? ' field--invalid' : '' ?>" for="edit-service-slug"><span class="field__label">Slug</span><input id="edit-service-slug" type="text" name="slug" value="<?= e($slug) ?>" maxlength="180" pattern="[a-z0-9-]+" required><?php if (isset($errors['slug'])): ?><small class="field__error"><?= e((string) $errors['slug']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['short_description']) ? ' field--invalid' : '' ?>" for="edit-service-short"><span class="field__label">Краткое описание</span><input id="edit-service-short" type="text" name="short_description" value="<?= e($shortDescription) ?>" maxlength="255"><?php if (isset($errors['short_description'])): ?><small class="field__error"><?= e((string) $errors['short_description']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['description']) ? ' field--invalid' : '' ?>" for="edit-service-description"><span class="field__label">Полное описание</span><textarea id="edit-service-description" name="description" rows="5"><?= e($description) ?></textarea><?php if (isset($errors['description'])): ?><small class="field__error"><?= e((string) $errors['description']) ?></small><?php endif; ?></label>
                    <div class="form-grid">
                        <label class="field<?= isset($errors['price_from']) ? ' field--invalid' : '' ?>" for="edit-service-price"><span class="field__label">Цена от, ₸</span><input id="edit-service-price" type="number" name="price_from" min="0" max="99999999.99" step="0.01" value="<?= e($priceFrom) ?>"><?php if (isset($errors['price_from'])): ?><small class="field__error"><?= e((string) $errors['price_from']) ?></small><?php endif; ?></label>
                        <label class="field<?= isset($errors['estimated_minutes']) ? ' field--invalid' : '' ?>" for="edit-service-minutes"><span class="field__label">Время, мин.</span><input id="edit-service-minutes" type="number" name="estimated_minutes" min="1" max="100000" step="1" value="<?= e($estimatedMinutes) ?>"><?php if (isset($errors['estimated_minutes'])): ?><small class="field__error"><?= e((string) $errors['estimated_minutes']) ?></small><?php endif; ?></label>
                    </div>
                    <div class="admin-toggle-row"><div><strong>Услуга опубликована</strong><small>Неактивные услуги скрыты из публичного каталога.</small><?php if (isset($errors['is_active'])): ?><small class="field__error"><?= e((string) $errors['is_active']) ?></small><?php endif; ?></div><span><input type="hidden" name="is_active" value="0"><input id="edit-service-active" type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>><label for="edit-service-active" class="sr-only">Опубликованная услуга</label></span></div>
                    <div class="request-form__actions"><a class="button button--secondary" href="/admin/services">Отмена</a><button class="button button--primary button--large" type="submit">Сохранить услугу</button></div>
                </form>
            </section>
            <aside class="request-create-aside" data-reveal><span class="request-create-aside__mark" aria-hidden="true">i</span><span class="eyebrow eyebrow--light">Публичный каталог</span><h2>Цена — это ориентир.</h2><ol><li><span>01</span><div><strong>Описание</strong><p>Объясняет клиенту, что входит в работу.</p></div></li><li><span>02</span><div><strong>Цена от</strong><p>Не заменяет смету после диагностики.</p></div></li><li><span>03</span><div><strong>Время</strong><p>Показывает ориентир, а не гарантию срока.</p></div></li></ol></aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
