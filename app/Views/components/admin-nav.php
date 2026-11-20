<?php
declare(strict_types=1);
$adminActiveSection = isset($adminActiveSection) && is_string($adminActiveSection) ? $adminActiveSection : 'overview';
$adminNavigation = [
    'overview' => ['label' => 'Обзор', 'href' => '/admin'],
    'requests' => ['label' => 'Заявки', 'href' => '/admin/requests'],
    'users' => ['label' => 'Пользователи', 'href' => '/admin/users'],
    'services' => ['label' => 'Услуги', 'href' => '/admin/services'],
    'categories' => ['label' => 'Категории', 'href' => '/admin/categories'],
    'guides' => ['label' => 'База знаний', 'href' => '/admin/guides'],
    'technicians' => ['label' => 'Профили мастеров', 'href' => '/admin/technicians'],
    'reviews' => ['label' => 'Отзывы', 'href' => '/admin/reviews'],
    'content' => ['label' => 'Контент', 'href' => '/admin/content/about'],
    'statistics' => ['label' => 'Статистика', 'href' => '/admin/statistics'],
];
?>
<nav class="workspace-nav admin-workspace-nav" aria-label="Разделы администратора" data-reveal>
    <?php foreach ($adminNavigation as $key => $item): ?>
        <a class="workspace-nav__link<?= $adminActiveSection === $key ? ' is-active' : '' ?>" href="<?= e($item['href']) ?>" <?= $adminActiveSection === $key ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
    <?php endforeach; ?>
</nav>
