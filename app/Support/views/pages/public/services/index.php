<?php
/**
 * Backend fallback catalogue page. Replaced by
 * app/Views/pages/public/services/index.php when the frontend view lands.
 *
 * @var array{type: string, message: string}|null $flash
 * @var list<array<string, mixed>> $categories
 * @var list<array<string, mixed>> $services
 * @var array{current_page: int, last_page: int, per_page: int, total: int} $pagination
 * @var array{q: string, category: string} $filters
 */
$appName = (string) config('app.name');
$pageUrl = static function (int $page) use ($filters): string {
    $params = ['page' => $page];
    if ($filters['q'] !== '') {
        $params['q'] = $filters['q'];
    }
    if ($filters['category'] !== '') {
        $params['category'] = $filters['category'];
    }

    return '/services?' . http_build_query($params);
};
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? ('Услуги — ' . $appName)) ?></title>
    <?php require __DIR__ . '/../../../partials/style.php'; ?>
</head>
<body>
<header class="fp-header">
    <div class="fp-shell">
        <a class="fp-brand" href="/"><?= e($appName) ?></a>
        <nav class="fp-nav" aria-label="Основная навигация">
            <a href="/">Главная</a>
            <a href="/services" aria-current="page">Услуги</a>
        </nav>
    </div>
</header>

<main class="fp-main">
    <div class="fp-shell">
        <?php if (is_array($flash)): ?>
            <div class="fp-alert fp-alert--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <h1>Каталог услуг</h1>
        <p class="fp-lead">Найдено: <?= (int) $pagination['total'] ?>. Цены ориентировочные; точная стоимость — после диагностики.</p>

        <form class="fp-form" method="get" action="/services" role="search">
            <div class="fp-field">
                <label for="q">Поиск</label>
                <input id="q" name="q" type="search" maxlength="100" value="<?= e($filters['q']) ?>" placeholder="Например, экран">
            </div>
            <div class="fp-field">
                <label for="category">Категория</label>
                <select id="category" name="category">
                    <option value="">Все категории</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['slug']) ?>"<?= $filters['category'] === $category['slug'] ? ' selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Показать</button>
        </form>

        <?php if ($services === []): ?>
            <div class="fp-empty">
                По запросу ничего не найдено. Измените фильтры или <a href="/services">сбросьте их</a>.
            </div>
        <?php else: ?>
            <ul class="fp-list">
                <?php foreach ($services as $service): ?>
                    <li>
                        <h3><a href="/services/<?= e($service['slug']) ?>"><?= e($service['name']) ?></a></h3>
                        <p class="fp-meta">
                            <span class="fp-badge"><?= e($service['category_name']) ?></span>
                            <?php if ($service['short_description'] !== null): ?>
                                <?= e($service['short_description']) ?>
                            <?php endif; ?>
                        </p>
                        <p class="fp-price">
                            <?= $service['price_from'] === null ? 'Цена по запросу' : 'от ' . e(format_money($service['price_from'])) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($pagination['last_page'] > 1): ?>
                <nav class="fp-pagination" aria-label="Постраничная навигация">
                    <?php if ($pagination['current_page'] > 1): ?>
                        <a href="<?= e($pageUrl($pagination['current_page'] - 1)) ?>" rel="prev">Назад</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $pagination['current_page'] - 2);
                    $end = min($pagination['last_page'], $pagination['current_page'] + 2);
                    for ($page = $start; $page <= $end; $page++):
                        ?>
                        <?php if ($page === $pagination['current_page']): ?>
                            <span aria-current="page"><?= $page ?></span>
                        <?php else: ?>
                            <a href="<?= e($pageUrl($page)) ?>"><?= $page ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
                        <a href="<?= e($pageUrl($pagination['current_page'] + 1)) ?>" rel="next">Вперёд</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<footer class="fp-footer">
    <div class="fp-shell">
        <span><?= e($appName) ?></span>
        <span><a href="/">На главную</a></span>
    </div>
</footer>
</body>
</html>
