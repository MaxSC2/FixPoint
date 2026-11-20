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
$unreadMessagesCount = isset($unreadMessagesCount) ? max(0, (int) $unreadMessagesCount) : 0;

$navigation = [
    'home' => ['label' => 'Главная', 'href' => '/'],
    'services' => ['label' => 'Услуги', 'href' => '/services'],
    'guides' => ['label' => 'Частые неисправности', 'href' => '/guides'],
    'masters' => ['label' => 'Мастера', 'href' => '/masters'],
    'about' => ['label' => 'О сервисе', 'href' => '/about'],
    'contacts' => ['label' => 'Контакты', 'href' => '/contacts'],
];

$accountUrl = match ($authUser['role'] ?? null) {
    'technician' => '/technician/requests',
    'admin' => '/admin',
    'customer' => '/account',
    default => '/account',
};
$requestAction = match ($authUser['role'] ?? null) {
    'customer' => ['href' => '/requests/create', 'label' => 'Создать заявку'],
    'technician' => ['href' => '/technician/requests', 'label' => 'К рабочей очереди'],
    'admin' => ['href' => '/admin/requests', 'label' => 'Открыть реестр'],
    default => ['href' => '/register', 'label' => 'Создать аккаунт'],
};
$userName = is_array($authUser) ? (string) ($authUser['full_name'] ?? 'Аккаунт') : '';
$userInitials = 'FP';
if ($userName !== '') {
    $nameParts = preg_split('/\s+/u', trim($userName)) ?: [];
    $letters = '';
    foreach (array_slice($nameParts, 0, 2) as $part) {
        $letters .= mb_substr($part, 0, 1);
    }
    if ($letters !== '') {
        $userInitials = mb_strtoupper($letters);
    }
}
?>
<!doctype html>
<html lang="ru" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f3f1e9">
    <meta name="description" content="FixPoint — сервисный центр. Оформление и отслеживание заявок на ремонт техники.">
    <title><?= e($pageTitle) ?> · FixPoint</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40' fill='none'%3E%3Cpath d='M20 3.5 35 12v16L20 36.5 5 28V12L20 3.5Z' stroke='%232563eb' stroke-width='3'/%3E%3Cpath d='M12 20.5 17.2 25.5 28.5 14' stroke='%232563eb' stroke-width='3.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='29.3' cy='10.8' r='2.2' fill='%23D9ED74'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Перейти к содержимому</a>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="/" aria-label="FixPoint — главная">
            <span class="brand__mark" aria-hidden="true">
                <svg viewBox="0 0 40 40" fill="none">
                    <path d="M20 3.5 35 12v16L20 36.5 5 28V12L20 3.5Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 20.5 17.2 25.5 28.5 14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="29.3" cy="10.8" r="2.2" fill="#D9ED74"/>
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
                <a class="header-messages" href="/messages"<?= $activeNav === 'messages' ? ' aria-current="page"' : '' ?>>
                    <span>Сообщения</span>
                    <?php if ($unreadMessagesCount > 0): ?>
                        <span class="header-messages__badge" aria-label="<?= e((string) $unreadMessagesCount) ?> непрочитанных"><?= e((string) min(99, $unreadMessagesCount)) ?><?= $unreadMessagesCount > 99 ? '+' : '' ?></span>
                    <?php endif; ?>
                </a>
                <a class="header-account" href="<?= e($accountUrl) ?>" title="<?= e($userName) ?>">
                    <span class="header-account__avatar" aria-hidden="true"><?= e($userInitials) ?></span>
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
