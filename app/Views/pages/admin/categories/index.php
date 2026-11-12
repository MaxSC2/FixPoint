<?php
declare(strict_types=1);

$pageTitle = 'Категории услуг';
$activeNav = 'admin';
$categories = isset($categories) && is_array($categories) ? array_values(array_filter($categories, 'is_array')) : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div><span class="eyebrow">Каталог / Справочник</span><h1>Категории услуг</h1><p>Структура каталога, названия и порядок отображения направлений обслуживания.</p></div>
            <span class="admin-record-count"><strong><?= e((string) count($categories)) ?></strong><small>в текущем списке</small></span>
        </div>
        <nav class="workspace-nav" aria-label="Разделы администратора" data-reveal>
            <a class="workspace-nav__link" href="/admin">Обзор</a><a class="workspace-nav__link" href="/admin/requests">Заявки</a><a class="workspace-nav__link" href="/admin/users">Пользователи</a><a class="workspace-nav__link" href="/admin/services">Услуги</a><a class="workspace-nav__link is-active" href="/admin/categories" aria-current="page">Категории</a>
        </nav>
        <div class="admin-crud-layout">
            <section class="staff-list-panel" data-reveal>
                <div class="request-list-panel__head"><div><span class="eyebrow">Структура каталога</span><h2>Список категорий</h2></div></div>
                <?php if ($categories !== []): ?>
                    <div class="admin-request-table-wrap">
                        <table class="admin-request-table">
                            <thead><tr><th>Категория</th><th>Slug</th><th>Порядок</th><th>Состояние</th><th><span class="sr-only">Редактировать</span></th></tr></thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <?php $categoryId = (string) ($category['id'] ?? ''); $active = !empty($category['is_active']); ?>
                                    <tr>
                                        <td><span class="admin-request-primary"><?= e((string) ($category['name'] ?? 'Без названия')) ?><small><?= e((string) ($category['description'] ?? '')) ?></small></span></td>
                                        <td><code><?= e((string) ($category['slug'] ?? '')) ?></code></td>
                                        <td><?= e((string) ($category['sort_order'] ?? 0)) ?></td>
                                        <td><span class="admin-state-chip <?= $active ? 'is-active' : 'is-inactive' ?>"><?= $active ? 'Активна' : 'Отключена' ?></span></td>
                                        <td><a class="admin-row-open" href="/admin/categories/<?= e(rawurlencode($categoryId)) ?>/edit" aria-label="Редактировать категорию <?= e((string) ($category['name'] ?? '')) ?>">↗</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <?php $title = 'Категорий пока нет'; $description = 'Создайте категории, чтобы сгруппировать опубликованные услуги.'; $actionLabel = ''; $actionHref = ''; require dirname(__DIR__, 3) . '/components/empty-state.php'; ?>
                <?php endif; ?>
            </section>
            <aside class="admin-create-panel" data-reveal>
                <span class="eyebrow">Новая запись</span><h2>Создать категорию</h2><p>Используйте короткое понятное имя. Slug используется в адресах и фильтрах каталога.</p>
                <?php if (isset($errors['general'])): ?><div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e((string) $errors['general']) ?></p></div><?php endif; ?>
                <form class="admin-record-form" method="post" action="/admin/categories">
                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                    <label class="field<?= isset($errors['name']) ? ' field--invalid' : '' ?>" for="new-category-name"><span class="field__label">Название</span><input id="new-category-name" type="text" name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" maxlength="120" required><?php if (isset($errors['name'])): ?><small class="field__error"><?= e((string) $errors['name']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['slug']) ? ' field--invalid' : '' ?>" for="new-category-slug"><span class="field__label">Slug</span><input id="new-category-slug" type="text" name="slug" value="<?= e((string) ($old['slug'] ?? '')) ?>" maxlength="140" pattern="[a-z0-9-]+" placeholder="computer-repair" required><?php if (isset($errors['slug'])): ?><small class="field__error"><?= e((string) $errors['slug']) ?></small><?php endif; ?></label>
                    <label class="field" for="new-category-description"><span class="field__label">Описание</span><textarea id="new-category-description" name="description" rows="3" maxlength="2000"><?= e((string) ($old['description'] ?? '')) ?></textarea></label>
                    <label class="field<?= isset($errors['sort_order']) ? ' field--invalid' : '' ?>" for="new-category-order"><span class="field__label">Порядок отображения</span><input id="new-category-order" type="number" name="sort_order" min="0" max="100000" step="1" value="<?= e((string) ($old['sort_order'] ?? '0')) ?>"><?php if (isset($errors['sort_order'])): ?><small class="field__error"><?= e((string) $errors['sort_order']) ?></small><?php endif; ?></label>
                    <button class="button button--primary button--large" type="submit">Создать категорию</button>
                </form>
            </aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
