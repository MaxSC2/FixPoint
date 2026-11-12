<?php
declare(strict_types=1);

$pageTitle = 'Услуги';
$activeNav = 'admin';
$services = isset($services) && is_array($services) ? array_values(array_filter($services, 'is_array')) : [];
$categories = isset($categories) && is_array($categories) ? array_values(array_filter($categories, 'is_array')) : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div><span class="eyebrow">Каталог / Справочник</span><h1>Услуги</h1><p>Публикация сервисных работ, категории, ориентировочные цены и время выполнения.</p></div>
            <span class="admin-record-count"><strong><?= e((string) count($services)) ?></strong><small>в текущем списке</small></span>
        </div>
        <nav class="workspace-nav" aria-label="Разделы администратора" data-reveal>
            <a class="workspace-nav__link" href="/admin">Обзор</a><a class="workspace-nav__link" href="/admin/requests">Заявки</a><a class="workspace-nav__link" href="/admin/users">Пользователи</a><a class="workspace-nav__link is-active" href="/admin/services" aria-current="page">Услуги</a><a class="workspace-nav__link" href="/admin/categories">Категории</a>
        </nav>
        <div class="admin-crud-layout">
            <section class="staff-list-panel" data-reveal>
                <div class="request-list-panel__head"><div><span class="eyebrow">Публичный каталог</span><h2>Список услуг</h2></div></div>
                <?php if ($services !== []): ?>
                    <div class="admin-request-table-wrap">
                        <table class="admin-request-table">
                            <thead><tr><th>Услуга</th><th>Категория</th><th>Цена от</th><th>Время</th><th>Состояние</th><th><span class="sr-only">Редактировать</span></th></tr></thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                    <?php
                                    $serviceId = (string) ($service['id'] ?? '');
                                    $price = $service['price_from'] ?? null;
                                    $priceLabel = is_numeric($price) && (float) $price >= 0 ? number_format((float) $price, 0, ',', ' ') . ' ₸' : 'Не задана';
                                    $minutes = $service['estimated_minutes'] ?? null;
                                    $timeLabel = is_numeric($minutes) && (int) $minutes > 0 ? (string) (int) $minutes . ' мин.' : 'Не задано';
                                    $active = !empty($service['is_active']);
                                    ?>
                                    <tr>
                                        <td><span class="admin-request-primary"><?= e((string) ($service['name'] ?? 'Без названия')) ?><small><?= e((string) ($service['slug'] ?? '')) ?></small></span></td>
                                        <td><?= e((string) ($service['category_name'] ?? '—')) ?></td>
                                        <td><?= e($priceLabel) ?></td>
                                        <td><?= e($timeLabel) ?></td>
                                        <td><span class="admin-state-chip <?= $active ? 'is-active' : 'is-inactive' ?>"><?= $active ? 'Опубликована' : 'Скрыта' ?></span></td>
                                        <td><a class="admin-row-open" href="/admin/services/<?= e(rawurlencode($serviceId)) ?>/edit" aria-label="Редактировать услугу <?= e((string) ($service['name'] ?? '')) ?>">↗</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <?php $title = 'Услуг пока нет'; $description = 'Добавьте услуги, чтобы они появились в публичном каталоге.'; $actionLabel = ''; $actionHref = ''; require dirname(__DIR__, 3) . '/components/empty-state.php'; ?>
                <?php endif; ?>
            </section>

            <aside class="admin-create-panel" data-reveal>
                <span class="eyebrow">Новая запись</span><h2>Добавить услугу</h2><p>Цена и время являются ориентирами. Окончательная смета определяется после диагностики.</p>
                <?php if (isset($errors['general'])): ?><div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div><?php endif; ?>
                <form class="admin-record-form" method="post" action="/admin/services">
                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                    <label class="field<?= isset($errors['category_id']) ? ' field--invalid' : '' ?>" for="new-service-category"><span class="field__label">Категория</span><select id="new-service-category" name="category_id" required><option value="">Выберите категорию</option><?php foreach ($categories as $category): ?><option value="<?= e((string) ($category['id'] ?? '')) ?>" <?= (string) ($old['category_id'] ?? '') === (string) ($category['id'] ?? '') ? 'selected' : '' ?>><?= e((string) ($category['name'] ?? '')) ?></option><?php endforeach; ?></select><?php if (isset($errors['category_id'])): ?><small class="field__error"><?= e((string) $errors['category_id']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['name']) ? ' field--invalid' : '' ?>" for="new-service-name"><span class="field__label">Название</span><input id="new-service-name" type="text" name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" maxlength="160" required><?php if (isset($errors['name'])): ?><small class="field__error"><?= e((string) $errors['name']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['slug']) ? ' field--invalid' : '' ?>" for="new-service-slug"><span class="field__label">Slug</span><input id="new-service-slug" type="text" name="slug" value="<?= e((string) ($old['slug'] ?? '')) ?>" maxlength="180" pattern="[a-z0-9-]+" placeholder="laptop-cleaning" required><?php if (isset($errors['slug'])): ?><small class="field__error"><?= e((string) $errors['slug']) ?></small><?php endif; ?></label>
                    <label class="field" for="new-service-short"><span class="field__label">Краткое описание</span><input id="new-service-short" type="text" name="short_description" value="<?= e((string) ($old['short_description'] ?? '')) ?>" maxlength="255"></label>
                    <label class="field" for="new-service-description"><span class="field__label">Полное описание</span><textarea id="new-service-description" name="description" rows="4"><?= e((string) ($old['description'] ?? '')) ?></textarea></label>
                    <div class="form-grid">
                        <label class="field<?= isset($errors['price_from']) ? ' field--invalid' : '' ?>" for="new-service-price"><span class="field__label">Цена от, ₸</span><input id="new-service-price" type="number" name="price_from" min="0" max="99999999.99" step="0.01" value="<?= e((string) ($old['price_from'] ?? '')) ?>"><?php if (isset($errors['price_from'])): ?><small class="field__error"><?= e((string) $errors['price_from']) ?></small><?php endif; ?></label>
                        <label class="field<?= isset($errors['estimated_minutes']) ? ' field--invalid' : '' ?>" for="new-service-minutes"><span class="field__label">Время, мин.</span><input id="new-service-minutes" type="number" name="estimated_minutes" min="1" max="100000" step="1" value="<?= e((string) ($old['estimated_minutes'] ?? '')) ?>"><?php if (isset($errors['estimated_minutes'])): ?><small class="field__error"><?= e((string) $errors['estimated_minutes']) ?></small><?php endif; ?></label>
                    </div>
                    <div class="admin-toggle-row"><div><strong>Услуга опубликована</strong><small>Неактивные услуги не отображаются в публичном каталоге.</small></div><span><input type="hidden" name="is_active" value="0"><input id="new-service-active" type="checkbox" name="is_active" value="1" <?= !array_key_exists('is_active', $old) || !empty($old['is_active']) ? 'checked' : '' ?>><label for="new-service-active" class="sr-only">Опубликовать услугу</label></span></div>
                    <button class="button button--primary button--large" type="submit">Создать услугу</button>
                </form>
            </aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
