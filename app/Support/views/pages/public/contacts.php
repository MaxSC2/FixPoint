<?php
/**
 * Backend fallback contacts page. Replaced by app/Views/pages/public/contacts.php
 * when the frontend view lands. Values come from configuration; missing values
 * are shown as explicit placeholders, never as invented contact details.
 *
 * @var string $contactAddress
 * @var string $contactPhone
 * @var string $contactEmail
 * @var string $workingHours
 */
$appName = (string) config('app.name');
$contactAddress = isset($contactAddress) ? (string) $contactAddress : '';
$contactPhone = isset($contactPhone) ? (string) $contactPhone : '';
$contactEmail = isset($contactEmail) ? (string) $contactEmail : '';
$workingHours = isset($workingHours) ? (string) $workingHours : '';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? ('Контакты — ' . $appName)) ?></title>
    <?php require __DIR__ . '/../../partials/style.php'; ?>
</head>
<body>
<header class="fp-header">
    <div class="fp-shell">
        <a class="fp-brand" href="/"><?= e($appName) ?></a>
        <nav class="fp-nav" aria-label="Основная навигация">
            <a href="/">Главная</a>
            <a href="/services">Услуги</a>
            <a href="/about">О сервисе</a>
            <a href="/contacts" aria-current="page">Контакты</a>
        </nav>
    </div>
</header>

<main class="fp-main">
    <div class="fp-shell">
        <h1>Контакты сервисного центра</h1>
        <div class="fp-card">
            <p><strong>Адрес:</strong> <?= $contactAddress !== '' ? e($contactAddress) : 'адрес будет опубликован после настройки проекта.' ?></p>
            <p><strong>Телефон:</strong> <?= $contactPhone !== '' ? e($contactPhone) : 'телефон пока не указан.' ?></p>
            <p><strong>Электронная почта:</strong> <?= $contactEmail !== '' ? e($contactEmail) : 'электронная почта пока не указана.' ?></p>
            <p><strong>График работы:</strong> <?= $workingHours !== '' ? e($workingHours) : 'график будет опубликован после настройки проекта.' ?></p>
        </div>
        <p class="fp-meta" style="margin-top: 18px">
            Это backend-fallback страница. Публичный шаблон появится из frontend-ветки.
        </p>
    </div>
</main>

<footer class="fp-footer">
    <div class="fp-shell">
        <span><?= e($appName) ?></span>
        <span><a href="/about">О сервисе</a></span>
    </div>
</footer>
</body>
</html>
