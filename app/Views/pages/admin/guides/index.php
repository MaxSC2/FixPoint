<?php
declare(strict_types=1);

$pageTitle = 'База знаний — администрирование';
$activeNav = 'admin';
$guides = isset($guides) && is_array($guides) ? $guides : [];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div><span class="eyebrow">Управление контентом / Модуль 5</span><h1>База знаний</h1><p>Создавайте безопасные инструкции и управляйте их публикацией. Черновики и архивные статьи не видны посетителям.</p></div>
            <a class="button button--primary button--large" href="/admin/guides/create">Новая статья <span aria-hidden="true">＋</span></a>
        </div>
        <nav class="workspace-nav" aria-label="Разделы администратора">
            <a class="workspace-nav__link" href="/admin">Обзор</a><a class="workspace-nav__link" href="/admin/requests">Заявки</a><a class="workspace-nav__link" href="/admin/users">Пользователи</a><a class="workspace-nav__link" href="/admin/services">Услуги</a><a class="workspace-nav__link" href="/admin/categories">Категории</a><a class="workspace-nav__link is-active" href="/admin/guides" aria-current="page">База знаний</a><a class="workspace-nav__link" href="/admin/technicians">Профили мастеров</a><a class="workspace-nav__link" href="/admin/content/about">Контент</a><a class="workspace-nav__link" href="/admin/statistics">Статистика</a>
        </nav>
        <section class="request-form-panel">
            <div class="request-form-panel__head"><span class="eyebrow">Материалы</span><h2>Все статьи</h2><p>Публикация доступна после заполнения обязательных разделов.</p></div>
            <?php if ($guides !== []): ?>
                <div class="table-scroll">
                    <table class="guide-admin-table">
                        <thead><tr><th>Название</th><th>Категория</th><th>Статус</th><th>Просмотры</th><th>Действия</th></tr></thead>
                        <tbody>
                        <?php foreach ($guides as $item): ?>
                            <tr>
                                <td><strong><?= e($item['title'] ?? '') ?></strong><br><small><?= e($item['slug'] ?? '') ?></small></td>
                                <td><?= e($item['category_label'] ?? '') ?></td>
                                <td><span class="guide-status"><?= e($item['status_label'] ?? '') ?></span></td>
                                <td><?= e((string) ($item['view_count'] ?? 0)) ?></td>
                                <td><div class="guide-admin-actions"><a class="button button--ghost button--small" href="/admin/guides/<?= e((string) $item['id']) ?>/edit">Изменить</a>
                                    <?php if (($item['status'] ?? '') !== 'archived'): ?>
                                    <form method="post" action="/admin/guides/<?= e((string) $item['id']) ?>/archive"><input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>"><button class="button button--ghost button--small" type="submit">В архив</button></form>
                                    <?php endif; ?>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state"><h2>Статей пока нет</h2><p>Создайте первую инструкцию для клиентов.</p><a class="button button--primary" href="/admin/guides/create">Добавить статью</a></div>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
