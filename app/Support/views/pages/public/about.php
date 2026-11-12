<?php
/**
 * Backend fallback about page. Replaced by app/Views/pages/public/about.php
 * when the frontend view lands.
 */
$appName = (string) config('app.name');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? ('О сервисе — ' . $appName)) ?></title>
    <?php require __DIR__ . '/../../partials/style.php'; ?>
</head>
<body>
<header class="fp-header">
    <div class="fp-shell">
        <a class="fp-brand" href="/"><?= e($appName) ?></a>
        <nav class="fp-nav" aria-label="Основная навигация">
            <a href="/">Главная</a>
            <a href="/services">Услуги</a>
            <a href="/about" aria-current="page">О сервисе</a>
            <a href="/contacts">Контакты</a>
        </nav>
    </div>
</header>

<main class="fp-main">
    <div class="fp-shell">
        <h1>О сервисе FixPoint</h1>
        <p class="fp-lead">
            FixPoint — учебный проект системы управления заявками сервисного центра.
            Приложение принимает обращения, фиксирует диагностику, согласует стоимость
            и хранит историю ремонта в одном месте.
        </p>
        <div class="fp-grid" style="margin-top: 20px">
            <section class="fp-card">
                <h2>Прозрачный процесс</h2>
                <p>Клиент видит статус своей заявки и доступную ему историю действий.</p>
            </section>
            <section class="fp-card">
                <h2>Согласование стоимости</h2>
                <p>Ремонт начинается после того, как смета подтверждена клиентом.</p>
            </section>
            <section class="fp-card">
                <h2>Роли и доступ</h2>
                <p>Клиент, мастер и администратор работают в своих разделах с серверной проверкой прав.</p>
            </section>
        </div>
        <p class="fp-meta" style="margin-top: 18px">
            Это backend-fallback страница. Публичный шаблон появится из frontend-ветки.
        </p>
    </div>
</main>

<footer class="fp-footer">
    <div class="fp-shell">
        <span><?= e($appName) ?></span>
        <span><a href="/contacts">Контакты</a></span>
    </div>
</footer>
</body>
</html>
