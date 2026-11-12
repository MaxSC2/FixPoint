<?php
/**
 * Backend fallback service detail page. Replaced by
 * app/Views/pages/public/services/show.php when the frontend view lands.
 *
 * @var array<string, mixed> $service
 */
$appName = (string) config('app.name');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $service['name']) ?></title>
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
        <p class="fp-meta"><a href="/services">← Все услуги</a></p>
        <h1><?= e($service['name']) ?></h1>
        <p><span class="fp-badge"><?= e($service['category_name']) ?></span></p>

        <?php if ($service['description'] !== null): ?>
            <div class="fp-card">
                <p><?= nl2br(e($service['description'])) ?></p>
            </div>
        <?php elseif ($service['short_description'] !== null): ?>
            <p class="fp-lead"><?= e($service['short_description']) ?></p>
        <?php endif; ?>

        <div class="fp-grid" style="margin-top: 20px">
            <section class="fp-card">
                <p class="fp-meta">Ориентировочная цена</p>
                <p class="fp-price" style="font-size: 1.3rem">
                    <?= $service['price_from'] === null ? 'По запросу' : 'от ' . e(format_money($service['price_from'])) ?>
                </p>
            </section>
            <section class="fp-card">
                <p class="fp-meta">Ориентировочное время</p>
                <p><?= $service['estimated_minutes'] === null ? 'Уточняется после диагностики' : e((string) $service['estimated_minutes']) . ' мин.' ?></p>
            </section>
        </div>

        <p class="fp-meta" style="margin-top: 18px">
            Точная стоимость и срок подтверждаются мастером после диагностики устройства.
        </p>
    </div>
</main>

<footer class="fp-footer">
    <div class="fp-shell">
        <span><?= e($appName) ?></span>
        <span><a href="/services">Каталог</a></span>
    </div>
</footer>
</body>
</html>
