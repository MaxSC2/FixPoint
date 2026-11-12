<?php
/**
 * Backend fallback home page. Replaced automatically by
 * app/Views/pages/public/home.php when the frontend view lands.
 *
 * @var array{type: string, message: string}|null $flash
 * @var array{categories_count: int, services_count: int} $stats
 * @var list<array<string, mixed>> $featuredServices
 * @var array{environment: string, php_version: string} $foundation
 */
$appName = (string) config('app.name');
$featuredServices = isset($featuredServices) && is_array($featuredServices) ? $featuredServices : [];
$stats = isset($stats) && is_array($stats)
    ? $stats
    : ['categories_count' => 0, 'services_count' => 0];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $appName) ?></title>
    <?php require __DIR__ . '/../../partials/style.php'; ?>
</head>
<body>
<header class="fp-header">
    <div class="fp-shell">
        <a class="fp-brand" href="/"><?= e($appName) ?></a>
        <nav class="fp-nav" aria-label="Основная навигация">
            <a href="/" aria-current="page">Главная</a>
            <a href="/services">Услуги</a>
            <a href="/about">О сервисе</a>
            <a href="/contacts">Контакты</a>
            <a href="/health">Статус</a>
        </nav>
    </div>
</header>

<main class="fp-main">
    <div class="fp-shell">
        <?php if (is_array($flash)): ?>
            <div class="fp-alert fp-alert--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <div class="fp-notice" role="note">
            Backend foundation FixPoint запущен. Публичные шаблоны из <code>app/Views</code> ещё не подключены,
            поэтому показана минимальная серверная страница. Данные ниже получены из MySQL, а не из макета.
        </div>

        <h1>Сервисный центр FixPoint</h1>
        <p class="fp-lead">
            Каркас приложения на PHP <?= e($foundation['php_version']) ?>, окружение
            <code><?= e($foundation['environment']) ?></code>. Каталог уже читает реальные категории и услуги из базы.
        </p>

        <div class="fp-grid" style="margin-top: 20px">
            <section class="fp-card">
                <p class="fp-meta">Активные категории</p>
                <p class="fp-stat"><?= (int) $stats['categories_count'] ?></p>
            </section>
            <section class="fp-card">
                <p class="fp-meta">Активные услуги</p>
                <p class="fp-stat"><?= (int) $stats['services_count'] ?></p>
            </section>
            <section class="fp-card">
                <p class="fp-meta">Проверка соединения</p>
                <p><span class="fp-badge">PDO / MySQL</span></p>
                <p class="fp-meta"><a href="/health">Открыть /health</a></p>
            </section>
        </div>

        <h2>Популярные услуги</h2>
        <?php if ($featuredServices === []): ?>
            <div class="fp-empty">Активных услуг пока нет. Запустите seed или добавьте данные в админке.</div>
        <?php else: ?>
            <ul class="fp-list">
                <?php foreach ($featuredServices as $service): ?>
                    <li>
                        <h3><a href="/services/<?= e($service['slug']) ?>"><?= e($service['name']) ?></a></h3>
                        <p class="fp-meta">
                            <?= e($service['category_name']) ?>
                            <?php if ($service['short_description'] !== null): ?>
                                — <?= e($service['short_description']) ?>
                            <?php endif; ?>
                        </p>
                        <p class="fp-price">
                            <?= $service['price_from'] === null ? 'Цена по запросу' : 'от ' . e(format_money($service['price_from'])) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>

<footer class="fp-footer">
    <div class="fp-shell">
        <span><?= e($appName) ?> — серверный каркас TASK-001</span>
        <span><a href="/services">Каталог услуг</a></span>
    </div>
</footer>
</body>
</html>
