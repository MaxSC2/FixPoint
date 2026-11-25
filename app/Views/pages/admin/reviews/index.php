<?php
$pageTitle = 'Модерация отзывов';
$activeNav = 'admin';
$adminActiveSection = 'reviews';
$reviews = isset($reviews) && is_array($reviews) ? $reviews : [];
$board = isset($board) && is_array($board) ? $board : [];
$impact = isset($impact) && is_array($impact) ? $impact : [];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact"><div><span class="eyebrow">Управление репутацией / Модерация</span><h1>Отзывы мастеров</h1><p>Скрытые отзывы не отображаются в профилях и не влияют на рейтинг. Запись и история заявки сохраняются.</p></div></div>
        <?php require dirname(__DIR__, 3) . '/components/admin-nav.php'; ?>

        <?php if ($board !== []): ?>
            <section class="admin-reputation" data-reveal>
                <div class="admin-reputation__head">
                    <div><span class="eyebrow">Доска почёта</span><h2>Сейчас на доске</h2></div>
                    <p>Рейтинг считается по средней оценке опубликованных отзывов с поправкой на общее среднее и число завершённых заявок. Правка или скрытие отзыва сразу меняет позицию мастера.</p>
                </div>
                <ol class="admin-reputation__list">
                    <?php foreach ($board as $row): ?>
                        <li class="admin-reputation__item">
                            <span class="admin-reputation__rank">#<?= e((string) ($row['position'] ?? 0)) ?></span>
                            <div class="admin-reputation__main">
                                <strong><?= e((string) ($row['full_name'] ?? 'Мастер FixPoint')) ?></strong>
                                <span><?= e((string) ($row['review_count'] ?? 0)) ?> отзывов · <?= e((string) ($row['completed_count'] ?? 0)) ?> завершённых заявок</span>
                            </div>
                            <span class="admin-reputation__rating">★ <?= e(number_format((float) ($row['average_rating'] ?? 0), 1, '.', '')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>

        <section class="request-form-panel">
            <?php if ($reviews !== []): ?>
                <div class="table-scroll"><table class="guide-admin-table">
                    <thead><tr><th>Заявка</th><th>Клиент / мастер</th><th>Оценка</th><th>Отзыв</th><th>Влияние на доску</th><th>Статус / действие</th></tr></thead>
                    <tbody><?php foreach ($reviews as $review): ?>
                        <?php
                        $reviewId = (int) $review['id'];
                        $reviewImpact = $impact[$reviewId] ?? null;
                        $current = is_array($reviewImpact) && ($reviewImpact['known'] ?? false) ? $reviewImpact['current'] : null;
                        $hidden = is_array($reviewImpact) && ($reviewImpact['known'] ?? false) ? $reviewImpact['hidden'] : null;
                        $published = ($review['status'] ?? '') === 'published';
                        ?>
                        <tr>
                            <td><?= e((string) $review['public_number']) ?></td>
                            <td><?= e((string) $review['customer_name']) ?><br><small><?= e((string) $review['technician_name']) ?></small></td>
                            <td><strong>★ <?= e((string) $review['rating']) ?>/5</strong></td>
                            <td><?= e((string) ($review['review_text'] ?? 'Без комментария')) ?><br><small><?= e((string) ($review['moderation_reason'] ?? '')) ?></small></td>
                            <td>
                                <?php if ($current !== null): ?>
                                    <div class="admin-review-impact">
                                        <span>Сейчас: ★ <?= e(number_format((float) ($current['average_rating'] ?? 0), 1, '.', '')) ?>, <?= e((string) ($current['review_count'] ?? 0)) ?> отзывов, место #<?= e((string) ($current['position'] ?? 0)) ?></span>
                                        <?php if ($published && $hidden !== null): ?>
                                            <span>Если скрыть: ★ <?= e(number_format((float) ($hidden['average_rating'] ?? 0), 1, '.', '')) ?>, <?= e((string) ($hidden['review_count'] ?? 0)) ?> отзывов, место #<?= e((string) ($hidden['position'] ?? 0)) ?></span>
                                        <?php else: ?>
                                            <span>Отзыв скрыт — на рейтинг не влияет.</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="admin-review-impact__empty">Нет данных</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="guide-status"><?= $published ? 'Опубликован' : 'Скрыт' ?></span>
                                <form class="review-moderation-form" method="post" action="/admin/reviews/<?= e((string) $reviewId) ?>/moderate" data-admin-moderation-form data-required-value="hidden">
                                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                    <label class="field"><span class="field__label">Статус</span><select name="status" data-moderation-status><option value="published" <?= $published ? 'selected' : '' ?>>Опубликовать</option><option value="hidden" <?= $published ? '' : 'selected' ?>>Скрыть</option></select></label>
                                    <label class="field"><span class="field__label">Причина (если скрываете)</span><input name="reason" maxlength="500" data-moderation-reason value="<?= e((string) ($review['moderation_reason'] ?? '')) ?>" placeholder="Укажите причину, если скрываете"></label>
                                    <button class="button button--ghost button--small" type="submit">Сохранить</button>
                                </form>
                                <form class="review-edit-form" method="post" action="/admin/reviews/<?= e((string) $reviewId) ?>/update">
                                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                    <label class="field"><span class="field__label">Оценка</span>
                                        <select name="rating">
                                            <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                                                <option value="<?= e((string) $rating) ?>" <?= (int) $review['rating'] === $rating ? 'selected' : '' ?>><?= e((string) $rating) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </label>
                                    <label class="field"><span class="field__label">Текст отзыва</span><textarea name="review_text" rows="3" maxlength="1500" placeholder="Комментарий клиента"><?= e((string) ($review['review_text'] ?? '')) ?></textarea></label>
                                    <label class="field"><span class="field__label">Причина правки</span><input name="reason" maxlength="500" value="<?= e((string) ($review['moderation_reason'] ?? '')) ?>" placeholder="Обязательно, если меняете оценку"></label>
                                    <button class="button button--ghost button--small" type="submit">Исправить отзыв</button>
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
