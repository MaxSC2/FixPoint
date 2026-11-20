<?php
declare(strict_types=1);

$pageTitle = 'Модерация отзывов';
$activeNav = 'admin';
$reviews = isset($reviews) && is_array($reviews) ? $reviews : [];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact"><div><span class="eyebrow">Управление репутацией / Модерация</span><h1>Отзывы мастеров</h1><p>Скрытые отзывы не отображаются в профилях и не влияют на рейтинг. Запись и история заявки сохраняются.</p></div></div>
        <nav class="workspace-nav" aria-label="Разделы администратора"><a class="workspace-nav__link" href="/admin">Обзор</a><a class="workspace-nav__link" href="/admin/guides">База знаний</a><a class="workspace-nav__link" href="/admin/technicians">Профили мастеров</a><a class="workspace-nav__link is-active" href="/admin/reviews" aria-current="page">Отзывы</a></nav>
        <section class="request-form-panel">
            <?php if ($reviews !== []): ?>
                <div class="table-scroll"><table class="guide-admin-table">
                    <thead><tr><th>Заявка</th><th>Клиент / мастер</th><th>Оценка</th><th>Отзыв</th><th>Статус / действие</th></tr></thead>
                    <tbody><?php foreach ($reviews as $review): ?>
                        <tr>
                            <td><?= e((string) $review['public_number']) ?></td>
                            <td><?= e((string) $review['customer_name']) ?><br><small><?= e((string) $review['technician_name']) ?></small></td>
                            <td><strong>★ <?= e((string) $review['rating']) ?>/5</strong></td>
                            <td><?= e((string) ($review['review_text'] ?? 'Без комментария')) ?><br><small><?= e((string) ($review['moderation_reason'] ?? '')) ?></small></td>
                            <td>
                                <span class="guide-status"><?= ($review['status'] ?? '') === 'published' ? 'Опубликован' : 'Скрыт' ?></span>
                                <form class="review-moderation-form" method="post" action="/admin/reviews/<?= e((string) $review['id']) ?>/moderate">
                                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                    <label class="field"><span class="field__label">Статус</span><select name="status"><option value="published" <?= ($review['status'] ?? '') === 'published' ? 'selected' : '' ?>>Опубликовать</option><option value="hidden" <?= ($review['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Скрыть</option></select></label>
                                    <label class="field"><span class="field__label">Причина (если скрываете)</span><input name="reason" maxlength="500" value="<?= e((string) ($review['moderation_reason'] ?? '')) ?>"></label>
                                    <button class="button button--ghost button--small" type="submit">Сохранить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php else: ?><div class="empty-state"><h2>Отзывов пока нет</h2><p>После завершения заявок клиенты смогут оценивать работу мастеров.</p></div><?php endif; ?>
        </section>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
