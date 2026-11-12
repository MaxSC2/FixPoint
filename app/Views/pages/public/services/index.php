<?php
declare(strict_types=1);

$pageTitle = 'Каталог услуг';
$activeNav = 'services';
$services = isset($services) && is_array($services) ? $services : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$query = (string) ($filters['q'] ?? '');
$selectedCategory = (string) ($filters['category'] ?? '');
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="page-hero page-hero--compact">
    <div class="container">
        <span class="eyebrow">Каталог FixPoint</span>
        <h1>Услуги сервисного центра</h1>
        <p>Выберите направление обслуживания. Итоговая стоимость определяется после диагностики и согласовывается до ремонта.</p>
        <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/">Главная</a><span aria-hidden="true">/</span><span aria-current="page">Услуги</span></nav>
    </div>
</section>

<section class="section section--catalog">
    <div class="container">
        <form class="catalog-filters" method="get" action="/services">
            <label class="field catalog-filters__search">
                <span class="sr-only">Поиск услуги</span>
                <span class="field__icon" aria-hidden="true">⌕</span>
                <input type="search" name="q" value="<?= e($query) ?>" placeholder="Например, чистка ноутбука">
            </label>
            <?php if ($categories !== []): ?>
                <label class="field catalog-filters__category">
                    <span class="sr-only">Категория услуги</span>
                    <select name="category">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $category): ?>
                            <?php
                            $category = is_array($category) ? $category : [];
                            $categorySlug = (string) ($category['slug'] ?? '');
                            ?>
                            <option value="<?= e($categorySlug) ?>" <?= $selectedCategory === $categorySlug ? 'selected' : '' ?>><?= e((string) ($category['name'] ?? 'Категория')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <button class="button button--primary" type="submit">Найти услуги</button>
            <?php if ($query !== '' || $selectedCategory !== ''): ?>
                <a class="button button--ghost" href="/services">Сбросить</a>
            <?php endif; ?>
        </form>

        <?php if ($services !== []): ?>
            <div class="catalog-results">
                <div class="catalog-results__heading"><h2>Доступные услуги</h2><span><?= e((string) count($services)) ?> в списке</span></div>
                <div class="service-grid">
                    <?php foreach ($services as $service): ?>
                        <?php require dirname(__DIR__, 3) . '/components/service-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <span class="empty-state__icon" aria-hidden="true">⌕</span>
                <h2>Услуги пока не найдены</h2>
                <p>Попробуйте изменить параметры поиска или вернитесь к полному каталогу. Новые услуги появятся здесь после публикации.</p>
                <a class="button button--secondary" href="/services">Показать все услуги</a>
            </div>
        <?php endif; ?>

        <aside class="catalog-note">
            <span class="catalog-note__icon" aria-hidden="true">i</span>
            <p>Цена «от» является ориентировочной. Точную стоимость мастер определит после диагностики и согласует с вами до начала ремонта.</p>
        </aside>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
