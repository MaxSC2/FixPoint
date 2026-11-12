<?php
declare(strict_types=1);

/**
 * Shared page header.
 *
 * Expected view data: pageTitle, activeNav, authUser, flash, csrfToken.
 * The backend bootstrap must provide e(string|null): string.
 */
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'FixPoint';
$activeNav = isset($activeNav) && is_string($activeNav) ? $activeNav : '';
$authUser = isset($authUser) && is_array($authUser) ? $authUser : null;
$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';

$navigation = [
    'home' => ['label' => 'Главная', 'href' => '/'],
    'services' => ['label' => 'Услуги', 'href' => '/services'],
    'about' => ['label' => 'О сервисе', 'href' => '/about'],
    'contacts' => ['label' => 'Контакты', 'href' => '/contacts'],
];

$accountUrl = match ($authUser['role'] ?? null) {
    'technician' => '/technician/requests',
    'admin' => '/admin',
    'customer' => '/account',
    default => '/account',
};
$userName = is_array($authUser) ? (string) ($authUser['full_name'] ?? 'Аккаунт') : '';
?>
<!doctype html>
<html lang="ru" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <meta name="description" content="FixPoint — сервисный центр. Оформление и отслеживание заявок на ремонт техники.">
    <title><?= e($pageTitle) ?> · FixPoint</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Перейти к содержимому</a>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="/" aria-label="FixPoint — главная">
            <span class="brand__mark" aria-hidden="true">
                <svg viewBox="0 0 36 36" fill="none">
                    <path d="M18 3.5 31.5 11v14L18 32.5 4.5 25V11L18 3.5Z" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M12 18.5 16 22.5 24.5 13.5" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="brand__word">Fix<span>Point</span></span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-navigation" data-nav-toggle>
            <span class="sr-only" data-nav-label>Открыть меню</span>
            <span class="nav-toggle__line"></span>
            <span class="nav-toggle__line"></span>
            <span class="nav-toggle__line"></span>
        </button>

        <nav class="site-nav" id="site-navigation" aria-label="Основная навигация" data-site-nav>
            <?php foreach ($navigation as $key => $item): ?>
                <a
                    class="site-nav__link<?= $activeNav === $key ? ' is-active' : '' ?>"
                    href="<?= e($item['href']) ?>"
                    <?= $activeNav === $key ? 'aria-current="page"' : '' ?>
                ><?= e($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="site-header__actions">
            <?php if ($authUser !== null): ?>
                <a class="header-account" href="<?= e($accountUrl) ?>">
                    <span class="header-account__avatar" aria-hidden="true">FP</span>
                    <span class="header-account__name"><?= e($userName) ?></span>
                </a>
                <form class="header-logout" method="post" action="/logout">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button class="button button--ghost button--small" type="submit">Выйти</button>
                </form>
            <?php else: ?>
                <a class="button button--ghost button--small header-login" href="/login">Войти</a>
                <a class="button button--primary button--small header-register" href="/register">Создать аккаунт</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main id="main-content" class="site-main">
    <?php require dirname(__DIR__) . '/components/flash.php'; ?>
