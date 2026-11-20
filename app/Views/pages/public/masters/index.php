<?php
declare(strict_types=1);

$pageTitle = 'Мастера FixPoint';
$activeNav = 'masters';
$masters = isset($masters) && is_array($masters) ? $masters : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$filters = isset($filters) && is_array($filters) ? $filters : ['q' => '', 'category' => ''];
$pagination = isset($pagination) && is_array($pagination) ? $pagination : ['page' => 1, 'pages' => 1, 'total' => 0];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading" data-reveal>
            <div><span class="eyebrow">Команда FixPoint / Специалисты</span><h1>Мастера</h1><p>Профили специалистов, направления работ и отзывы клиентов по завершённым заявкам.</p></div>
            <a class="button button--primary button--large" href="/requests/create">Создать заявку <span aria-hidden="true">↗</span></a>
        </div>
        <form class="guide-search" action="/masters" method="get" role="search">
            <label class="field" for="master-search"><span class="field__label">Поиск мастера или специализации</span><input id="master-search" type="search" name="q" maxlength="100" value="<?= e($filters['q']) ?>" placeholder="Имя, описание, направление"></label>
            <label class="field" for="master-category"><span class="field__label">Специализация</span><select id="master-category" name="category"><option value="">Все направления</option><?php foreach ($categories as $category): ?><option value="<?= e($category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
            <button class="button button--primary" type="submit">Найти</button>
        </form>
        <?php if ($masters !== []): ?>
            <div class="master-grid" data-reveal>
                <?php foreach ($masters as $master): ?>
                    <?php
                    $name = (string) ($master['full_name'] ?? 'Мастер FixPoint');
                    $parts = preg_split('/\s+/u', trim($name)) ?: [];
                    $initials = '';
                    foreach (array_slice($parts, 0, 2) as $part) { $initials .= mb_substr($part, 0, 1); }
                    $count = (int) ($master['review_count'] ?? 0);
                    ?>
                    <article class="master-card">
                        <div class="master-card__top"><div class="master-avatar" aria-hidden="true"><?= e(mb_strtoupper($initials !== '' ? $initials : 'FP')) ?></div><span class="master-card__orders"><?= e((string) ($master['completed_count'] ?? 0)) ?> завершённых заявок</span></div>
                        <h2><?= e($name) ?></h2>
                        <p class="master-card__headline"><?= e((string) ($master['headline'] ?? 'Специалист FixPoint')) ?></p>
                        <p class="master-card__specialties"><?= e((string) ($master['specialties'] ?? 'Специализация не указана')) ?></p>
                        <div class="master-card__rating">
                            <?php if ($count > 0): ?><strong>★ <?= e(number_format((float) $master['average_rating'], 1, '.', '')) ?></strong><span><?= e((string) $count) ?> отзывов</span>
                            <?php else: ?><span>Пока без отзывов</span><?php endif; ?>
                        </div>
                        <a class="button button--secondary" href="/masters/<?= e((string) $master['technician_id']) ?>">Профиль мастера <span aria-hidden="true">↗</span></a>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if (($pagination['pages'] ?? 1) > 1): ?>
                <?php
                $page = (int) ($pagination['page'] ?? 1);
                $query = http_build_query(array_filter(['q' => $filters['q'], 'category' => $filters['category']], static fn ($value) => $value !== ''));
                $prefix = '/masters' . ($query !== '' ? '?' . $query . '&' : '?');
                ?>
                <nav class="pagination" aria-label="Страницы мастеров">
                    <?php if ($page > 1): ?><a class="button button--ghost" href="<?= e($prefix . 'page=' . ($page - 1)) ?>">Назад</a><?php endif; ?>
                    <span>Страница <?= e((string) $page) ?> из <?= e((string) $pagination['pages']) ?></span>
                    <?php if ($page < (int) $pagination['pages']): ?><a class="button button--ghost" href="<?= e($prefix . 'page=' . ($page + 1)) ?>">Далее</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state"><h2>Мастера пока не найдены</h2><p>Попробуйте изменить поиск или фильтр специализации.</p></div>
        <?php endif; ?>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
