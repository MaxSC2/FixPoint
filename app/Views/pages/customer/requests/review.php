<?php
declare(strict_types=1);

$pageTitle = 'Оценка мастера';
$activeNav = 'requests';
$request = isset($request) && is_array($request) ? $request : [];
$existingReview = isset($existingReview) && is_array($existingReview) ? $existingReview : null;
$canReview = !empty($canReview);
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$requestId = (int) ($request['id'] ?? 0);
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/requests">Мои заявки</a><span aria-hidden="true">/</span><a href="/requests/<?= e((string) $requestId) ?>"><?= e((string) ($request['public_number'] ?? 'Заявка')) ?></a><span aria-hidden="true">/</span><span aria-current="page">Оценка мастера</span></nav>
        <div class="workspace-heading workspace-heading--compact"><div><span class="eyebrow">Обратная связь / Завершённый ремонт</span><h1>Оцените работу мастера</h1><p><?= e((string) ($request['public_number'] ?? '')) ?> · <?= e((string) ($request['technician_name'] ?? 'Мастер')) ?></p></div></div>
        <?php if ($existingReview !== null): ?>
            <section class="request-form-panel">
                <div class="master-review__head"><strong>Ваш отзыв отправлен</strong><span>★ <?= e((string) $existingReview['rating']) ?>/5</span></div>
                <p><?= e((string) ($existingReview['review_text'] ?? 'Оценка оставлена без комментария.')) ?></p>
                <p class="field__help">Для одной завершённой заявки разрешён один отзыв. Он учитывается в рейтинге, пока опубликован.</p>
                <a class="button button--ghost" href="/requests/<?= e((string) $requestId) ?>">Вернуться к заявке</a>
            </section>
        <?php elseif ($canReview): ?>
            <section class="request-form-panel">
                <form class="request-form" method="post" action="/requests/<?= e((string) $requestId) ?>/review">
                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                    <fieldset class="review-rating-picker">
                        <legend>Общая оценка</legend>
                        <p>1 — неудовлетворительно, 5 — отлично.</p>
                        <div class="review-rating-picker__options">
                            <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                                <label><input type="radio" name="rating" value="<?= e((string) $rating) ?>" <?= (string) ($old['rating'] ?? '') === (string) $rating ? 'checked' : '' ?> required><span>★ <?= e((string) $rating) ?></span></label>
                            <?php endfor; ?>
                        </div>
                        <?php if (isset($errors['rating'])): ?><small class="field__error"><?= e($errors['rating']) ?></small><?php endif; ?>
                    </fieldset>
                    <label class="field" for="review-text"><span class="field__label">Комментарий (необязательно)</span><textarea id="review-text" name="review_text" rows="5" maxlength="1500" placeholder="Что было сделано хорошо? Что можно улучшить?"><?= e((string) ($old['review_text'] ?? '')) ?></textarea><small class="field__help">До 1500 символов. Не указывайте личные данные и пароли.</small><?php if (isset($errors['review_text'])): ?><small class="field__error"><?= e($errors['review_text']) ?></small><?php endif; ?></label>
                    <?php if (isset($errors['review'])): ?><div class="alert alert--error" role="alert"><p><?= e($errors['review']) ?></p></div><?php endif; ?>
                    <div class="request-form__actions"><button class="button button--primary button--large" type="submit">Отправить отзыв</button><a class="button button--ghost" href="/requests/<?= e((string) $requestId) ?>">Отмена</a></div>
                </form>
            </section>
        <?php else: ?>
            <div class="empty-state"><h2>Оценка пока недоступна</h2><p>Оставить отзыв можно только по завершённой заявке с назначенным мастером.</p><a class="button button--ghost" href="/requests/<?= e((string) $requestId) ?>">Вернуться к заявке</a></div>
        <?php endif; ?>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
