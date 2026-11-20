<?php
declare(strict_types=1);

$pageTitle = 'Контент сайта';
$activeNav = 'admin';
$adminActiveSection = 'content';
$content = isset($content) && is_array($content) ? $content : [];
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$value = static fn (string $key): string => (string) ($old[$key] ?? $content[$key] ?? '');
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div>
                <span class="eyebrow">Управление сайтом / Публичный контент</span>
                <h1>Страница «О сервисе»</h1>
                <p>Изменяйте текст публичной страницы. Сохранённые значения сразу отображаются посетителям.</p>
                <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/admin">Администрирование</a><span aria-hidden="true">/</span><span aria-current="page">Контент</span></nav>
            </div>
            <a class="button button--ghost" href="/about">Открыть страницу <span aria-hidden="true">↗</span></a>
        </div>

        <?php require dirname(__DIR__, 3) . '/components/admin-nav.php'; ?>

        <section class="request-form-panel admin-content-editor" data-reveal>
            <div class="request-form-panel__head">
                <span class="eyebrow">Редактор блока About</span>
                <h2>Основная информация</h2>
                <p>Используйте обычный текст. HTML и скрипты не выполняются: публичная страница экранирует содержимое.</p>
            </div>
            <form class="request-form admin-record-form" method="post" action="/admin/content/about">
                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                <label class="field<?= isset($errors['title']) ? ' field--invalid' : '' ?>" for="about-title">
                    <span class="field__label">Главный заголовок</span>
                    <input id="about-title" name="title" type="text" maxlength="180" value="<?= e($value('title')) ?>" required>
                    <?php if (isset($errors['title'])): ?><small class="field__error"><?= e((string) $errors['title']) ?></small><?php endif; ?>
                </label>
                <label class="field<?= isset($errors['lead']) ? ' field--invalid' : '' ?>" for="about-lead">
                    <span class="field__label">Краткое описание</span>
                    <textarea id="about-lead" name="lead" rows="3" maxlength="255" required><?= e($value('lead')) ?></textarea>
                    <?php if (isset($errors['lead'])): ?><small class="field__error"><?= e((string) $errors['lead']) ?></small><?php endif; ?>
                </label>
                <label class="field<?= isset($errors['section_heading']) ? ' field--invalid' : '' ?>" for="about-section-heading">
                    <span class="field__label">Заголовок второго блока</span>
                    <input id="about-section-heading" name="section_heading" type="text" maxlength="180" value="<?= e($value('section_heading')) ?>" required>
                    <?php if (isset($errors['section_heading'])): ?><small class="field__error"><?= e((string) $errors['section_heading']) ?></small><?php endif; ?>
                </label>
                <label class="field<?= isset($errors['section_body']) ? ' field--invalid' : '' ?>" for="about-section-body">
                    <span class="field__label">Основной текст</span>
                    <textarea id="about-section-body" name="section_body" rows="9" maxlength="4000" required><?= e($value('section_body')) ?></textarea>
                    <small class="field__help">Разделяйте абзацы пустой строкой. Максимум 4 000 символов.</small>
                    <?php if (isset($errors['section_body'])): ?><small class="field__error"><?= e((string) $errors['section_body']) ?></small><?php endif; ?>
                </label>
                <div class="request-form__actions">
                    <button class="button button--primary button--large" type="submit">Сохранить изменения</button>
                    <a class="button button--ghost" href="/admin">Назад в админку</a>
                </div>
            </form>
        </section>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
