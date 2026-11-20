<?php
declare(strict_types=1);

$pageTitle = 'Модерация профилей мастеров';
$activeNav = 'admin';
$adminActiveSection = 'technicians';
$technicians = isset($technicians) && is_array($technicians) ? $technicians : [];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact">
            <div>
                <span class="eyebrow">Управление сообществом / Модерация</span>
                <h1>Профили мастеров</h1>
                <p>Администратор может скрыть профиль независимо от настройки мастера. Мастер не сможет самостоятельно отменить это решение.</p>
            </div>
        </div>
        <?php require dirname(__DIR__, 3) . '/components/admin-nav.php'; ?>
        <section class="request-form-panel">
            <?php if ($technicians !== []): ?>
                <div class="table-scroll">
                    <table class="guide-admin-table">
                        <thead><tr><th>Мастер</th><th>Публичность</th><th>Репутация</th><th>Административное решение</th></tr></thead>
                        <tbody>
                        <?php foreach ($technicians as $technician): ?>
                            <?php
                            $adminVisible = (int) ($technician['admin_is_visible'] ?? 1) === 1;
                            $ownerVisible = (int) ($technician['owner_is_public'] ?? 1) === 1;
                            $active = (int) ($technician['is_active'] ?? 0) === 1;
                            $publicNow = $active && $ownerVisible && $adminVisible;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) $technician['full_name']) ?></strong>
                                    <br><small>ID <?= e((string) $technician['technician_id']) ?></small>
                                    <br><?php if ($publicNow): ?>
                                        <a href="/masters/<?= e((string) $technician['technician_id']) ?>">Открыть публичный профиль</a>
                                    <?php else: ?>
                                        <span class="admin-muted-action">Публичный профиль скрыт</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$active): ?><span class="guide-status">Учётная запись отключена</span>
                                    <?php elseif (!$adminVisible): ?><span class="guide-status">Скрыт администратором</span>
                                    <?php elseif (!$ownerVisible): ?><span class="guide-status">Скрыт мастером</span>
                                    <?php else: ?><span class="guide-status">Публичный</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int) ($technician['review_count'] ?? 0) > 0): ?>
                                        <strong>★ <?= e(number_format((float) $technician['average_rating'], 1, '.', '')) ?></strong>
                                        <br><small><?= e((string) $technician['review_count']) ?> отзывов</small>
                                    <?php else: ?>
                                        <small>Без отзывов</small>
                                    <?php endif; ?>
                                    <br><small><?= e((string) $technician['completed_count']) ?> завершённых заявок</small>
                                </td>
                                <td>
                                    <form class="profile-moderation-form" method="post" action="/admin/technicians/<?= e((string) $technician['technician_id']) ?>/visibility" data-admin-moderation-form data-required-value="0">
                                        <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                                        <label class="field">
                                            <span class="field__label">Видимость для администратора</span>
                                            <select name="is_visible" data-moderation-status>
                                                <option value="1" <?= $adminVisible ? 'selected' : '' ?>>Разрешить публичный показ</option>
                                                <option value="0" <?= !$adminVisible ? 'selected' : '' ?>>Скрыть профиль</option>
                                            </select>
                                        </label>
                                        <label class="field">
                                            <span class="field__label">Причина скрытия</span>
                                            <input name="reason" maxlength="500" data-moderation-reason value="<?= e((string) ($technician['admin_moderation_reason'] ?? '')) ?>" placeholder="Обязательна при скрытии">
                                        </label>
                                        <button class="button button--ghost button--small" type="submit">Сохранить</button>
                                    </form>
                                    <?php if (!empty($technician['admin_moderation_reason'])): ?>
                                        <small class="field__help">Последняя причина: <?= e((string) $technician['admin_moderation_reason']) ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state"><h2>Мастеров пока нет</h2><p>Когда в системе появятся учётные записи мастеров, здесь будет доступна их модерация.</p></div>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
