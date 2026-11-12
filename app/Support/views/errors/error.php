<?php
/**
 * Neutral HTTP error page. Never renders exception messages, SQL, file paths
 * or stack traces. A correlation id is shown for 500 errors and logged.
 *
 * @var int $status
 * @var string $title
 * @var string $message
 * @var string|null $errorId
 */
$appName = (string) config('app.name');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? ($title . ' — ' . $appName)) ?></title>
    <?php require __DIR__ . '/../partials/style.php'; ?>
</head>
<body>
<header class="fp-header">
    <div class="fp-shell">
        <a class="fp-brand" href="/"><?= e($appName) ?></a>
        <nav class="fp-nav" aria-label="Основная навигация">
            <a href="/">Главная</a>
            <a href="/services">Услуги</a>
        </nav>
    </div>
</header>

<main class="fp-main">
    <div class="fp-shell">
        <p class="fp-meta">HTTP <?= (int) $status ?></p>
        <h1><?= e($title) ?></h1>
        <p class="fp-lead"><?= e($message) ?></p>

        <?php if ($errorId !== null && $errorId !== ''): ?>
            <p class="fp-meta">Идентификатор ошибки: <code><?= e($errorId) ?></code></p>
        <?php endif; ?>

        <p><a class="fp-button" href="/" style="padding: 9px 14px">На главную</a></p>
    </div>
</main>

<footer class="fp-footer">
    <div class="fp-shell">
        <span><?= e($appName) ?></span>
        <span>Серверный каркас</span>
    </div>
</footer>
</body>
</html>
