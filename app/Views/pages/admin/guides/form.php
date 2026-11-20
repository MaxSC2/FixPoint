<?php
declare(strict_types=1);

$pageTitle = (string) ($pageTitle ?? 'Статья базы знаний');
$activeNav = 'admin';
$adminActiveSection = 'guides';
$guide = isset($guide) && is_array($guide) ? $guide : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$value = static fn (string $key, string $default = ''): string => (string) ($old[$key] ?? $guide[$key] ?? $default);
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <?php require dirname(__DIR__, 3) . '/components/admin-nav.php'; ?>
        <div class="workspace-heading workspace-heading--compact"><div><span class="eyebrow">База знаний / Редактор</span><h1><?= e($pageTitle) ?></h1><p>Пишите только проверенные безопасные рекомендации. Не вставляйте HTML-код.</p></div><a class="button button--ghost" href="/admin/guides">К списку статей</a></div>
        <section class="request-form-panel">
            <form class="request-form admin-record-form" method="post" action="<?= e($formAction ?? '/admin/guides') ?>">
                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                <div class="guide-form-grid">
                    <label class="field field--wide<?= isset($errors['title']) ? ' field--invalid' : '' ?>" for="guide-title"><span class="field__label">Название статьи</span><input id="guide-title" name="title" maxlength="180" required value="<?= e($value('title')) ?>"><?php if (isset($errors['title'])): ?><small class="field__error"><?= e($errors['title']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['slug']) ? ' field--invalid' : '' ?>" for="guide-slug"><span class="field__label">URL-адрес (латиницей)</span><input id="guide-slug" name="slug" maxlength="190" value="<?= e($value('slug')) ?>" placeholder="можно оставить пустым"><small class="field__help">Если поле пустое, адрес будет создан из названия.</small><?php if (isset($errors['slug'])): ?><small class="field__error"><?= e($errors['slug']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['category_slug']) ? ' field--invalid' : '' ?>" for="guide-category"><span class="field__label">Категория</span><select id="guide-category" name="category_slug" required><option value="">Выберите категорию</option><?php foreach ($categories as $category): ?><option value="<?= e($category['slug']) ?>" <?= $value('category_slug') === $category['slug'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select><?php if (isset($errors['category_slug'])): ?><small class="field__error"><?= e($errors['category_slug']) ?></small><?php endif; ?></label>
                    <label class="field<?= isset($errors['device_type']) ? ' field--invalid' : '' ?>" for="guide-device"><span class="field__label">Тип устройства</span><input id="guide-device" name="device_type" maxlength="100" required value="<?= e($value('device_type')) ?>"><?php if (isset($errors['device_type'])): ?><small class="field__error"><?= e($errors['device_type']) ?></small><?php endif; ?></label>
                    <label class="field field--wide<?= isset($errors['summary']) ? ' field--invalid' : '' ?>" for="guide-summary"><span class="field__label">Краткое описание</span><textarea id="guide-summary" name="summary" rows="2" maxlength="420" required><?= e($value('summary')) ?></textarea><?php if (isset($errors['summary'])): ?><small class="field__error"><?= e($errors['summary']) ?></small><?php endif; ?></label>
                    <label class="field field--wide<?= isset($errors['symptoms']) ? ' field--invalid' : '' ?>" for="guide-symptoms"><span class="field__label">Признаки проблемы</span><textarea id="guide-symptoms" name="symptoms" rows="3" maxlength="2500" required><?= e($value('symptoms')) ?></textarea><?php if (isset($errors['symptoms'])): ?><small class="field__error"><?= e($errors['symptoms']) ?></small><?php endif; ?></label>
                    <label class="field field--wide<?= isset($errors['possible_causes']) ? ' field--invalid' : '' ?>" for="guide-causes"><span class="field__label">Возможные причины</span><textarea id="guide-causes" name="possible_causes" rows="3" maxlength="2500" required><?= e($value('possible_causes')) ?></textarea><?php if (isset($errors['possible_causes'])): ?><small class="field__error"><?= e($errors['possible_causes']) ?></small><?php endif; ?></label>
                    <label class="field field--wide<?= isset($errors['steps']) ? ' field--invalid' : '' ?>" for="guide-steps"><span class="field__label">Безопасные шаги проверки</span><textarea id="guide-steps" name="steps" rows="7" required><?= e($value('steps_text', $value('steps'))) ?></textarea><small class="field__help">От 3 до 10 шагов. Каждый шаг вводите с новой строки. Один шаг — одно понятное действие.</small><?php if (isset($errors['steps'])): ?><small class="field__error"><?= e($errors['steps']) ?></small><?php endif; ?></label>
                    <label class="field field--wide<?= isset($errors['do_not']) ? ' field--invalid' : '' ?>" for="guide-do-not"><span class="field__label">Что делать нельзя</span><textarea id="guide-do-not" name="do_not" rows="3" maxlength="2000" required><?= e($value('do_not')) ?></textarea><?php if (isset($errors['do_not'])): ?><small class="field__error"><?= e($errors['do_not']) ?></small><?php endif; ?></label>
                    <label class="field field--wide<?= isset($errors['when_to_contact']) ? ' field--invalid' : '' ?>" for="guide-contact"><span class="field__label">Когда обращаться к мастеру</span><textarea id="guide-contact" name="when_to_contact" rows="3" maxlength="2000" required><?= e($value('when_to_contact')) ?></textarea><?php if (isset($errors['when_to_contact'])): ?><small class="field__error"><?= e($errors['when_to_contact']) ?></small><?php endif; ?></label>
                    <label class="field" for="guide-status"><span class="field__label">Статус статьи</span><select id="guide-status" name="status"><option value="draft" <?= $value('status', 'draft') === 'draft' ? 'selected' : '' ?>>Черновик</option><option value="published" <?= $value('status') === 'published' ? 'selected' : '' ?>>Опубликована</option></select></label>
                </div>
                <div class="request-form__actions"><button class="button button--primary button--large" type="submit">Сохранить статью</button><a class="button button--ghost" href="/admin/guides">Отмена</a></div>
            </form>
        </section>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
