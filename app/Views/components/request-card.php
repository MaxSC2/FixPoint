<?php
declare(strict_types=1);

/**
 * Presentational request summary.
 *
 * Input: request array following docs/10-INTEGRATION-CONTRACT.md.
 * The backend must already have scoped this record to the current user.
 */
$_requestCard = isset($request) && is_array($request) ? $request : [];
$_requestCardId = (string) ($_requestCard['id'] ?? '');
$_requestCardNumber = (string) ($_requestCard['public_number'] ?? 'Заявка');
$_requestCardStatus = (string) ($_requestCard['status'] ?? '');
$_requestCardType = trim((string) ($_requestCard['device_type'] ?? 'Устройство'));
$_requestCardBrand = trim((string) ($_requestCard['device_brand'] ?? ''));
$_requestCardModel = trim((string) ($_requestCard['device_model'] ?? ''));
$_requestCardTitle = trim(implode(' ', array_filter([$_requestCardBrand, $_requestCardModel])));
if ($_requestCardTitle === '') {
    $_requestCardTitle = $_requestCardType;
}
$_requestCardDate = (string) ($_requestCard['updated_at'] ?? $_requestCard['created_at'] ?? '');
$_requestCardDateLabel = 'Дата не указана';
if ($_requestCardDate !== '') {
    try {
        $_requestCardDateLabel = (new DateTimeImmutable($_requestCardDate))->format('d.m.Y');
    } catch (Throwable) {
        $_requestCardDateLabel = 'Дата уточняется';
    }
}
$_requestCardHref = $_requestCardId !== '' ? '/requests/' . rawurlencode($_requestCardId) : '/requests';
?>
<article class="request-card">
    <div class="request-card__top">
        <span class="request-card__number"><?= e($_requestCardNumber) ?></span>
        <?php $status = $_requestCardStatus; require __DIR__ . '/status-badge.php'; ?>
    </div>
    <div class="request-card__main">
        <div class="request-card__device-mark" aria-hidden="true">
            <svg viewBox="0 0 40 40" fill="none">
                <rect x="7" y="7" width="26" height="20" rx="2" stroke="currentColor" stroke-width="1.6"/>
                <path d="M3.5 32h33L33 27H7l-3.5 5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="request-card__copy">
            <h2><a href="<?= e($_requestCardHref) ?>"><?= e($_requestCardTitle) ?></a></h2>
            <p><?= e($_requestCardType) ?><?php if (!empty($_requestCard['service']['name'])): ?> · <?= e((string) $_requestCard['service']['name']) ?><?php endif; ?></p>
        </div>
        <a class="request-card__arrow" href="<?= e($_requestCardHref) ?>" aria-label="Открыть заявку <?= e($_requestCardNumber) ?>">↗</a>
    </div>
    <div class="request-card__bottom">
        <span>Последнее обновление</span>
        <time><?= e($_requestCardDateLabel) ?></time>
    </div>
</article>
