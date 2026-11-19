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
$_requestCardRole = isset($authUser['role']) ? (string) $authUser['role'] : '';
$_requestCardBase = in_array($_requestCardRole, ['technician', 'admin'], true)
    ? '/technician/requests/'
    : '/requests/';
$_requestCardHref = $_requestCardId !== '' ? $_requestCardBase . rawurlencode($_requestCardId) : '/requests';
$_requestCardDevice = mb_strtolower($_requestCardType);
$_requestCardIcon = match (true) {
    str_contains($_requestCardDevice, 'смартфон'), str_contains($_requestCardDevice, 'телефон') => 'phone',
    str_contains($_requestCardDevice, 'планшет') => 'tablet',
    str_contains($_requestCardDevice, 'монитор') => 'monitor',
    str_contains($_requestCardDevice, 'принтер'), str_contains($_requestCardDevice, 'мфу') => 'printer',
    str_contains($_requestCardDevice, 'консол'), str_contains($_requestCardDevice, 'игров') => 'console',
    str_contains($_requestCardDevice, 'настольн'), str_contains($_requestCardDevice, 'компьютер') => 'desktop',
    str_contains($_requestCardDevice, 'другое') => 'generic',
    default => 'laptop',
};
?>
<article class="request-card">
    <div class="request-card__top">
        <span class="request-card__number"><?= e($_requestCardNumber) ?></span>
        <?php $status = $_requestCardStatus; require __DIR__ . '/status-badge.php'; ?>
    </div>
    <div class="request-card__main">
        <?php
        $_requestCardIconSvg = match ($_requestCardIcon) {
            'phone' => '<rect x="12" y="5" width="16" height="30" rx="3" stroke="currentColor" stroke-width="1.6"/><path d="M17.5 31.5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
            'tablet' => '<rect x="9" y="5" width="22" height="30" rx="2.5" stroke="currentColor" stroke-width="1.6"/><path d="M17.5 31.5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
            'monitor' => '<rect x="5.5" y="7" width="29" height="20" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M20 27v6M14.5 33h11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
            'desktop' => '<rect x="5.5" y="6" width="22" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M10 28h13M16.5 22v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><rect x="30" y="12" width="5" height="16" rx="1.5" stroke="currentColor" stroke-width="1.6"/>',
            'printer' => '<path d="M12 14V5.5h16V14" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><rect x="6.5" y="14" width="27" height="13" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M12 23h16v10H12z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
            'console' => '<path d="M13 13.5h14c2.8 0 5 2.2 5.4 5.5l1 6.3c.3 2-1.2 3.7-3.1 3.7-1.3 0-2.4-.7-3.3-1.9L25 24H15l-2 3.1c-.9 1.2-2 1.9-3.3 1.9-1.9 0-3.4-1.7-3.1-3.7l1-6.3c.4-3.3 2.6-5.5 5.4-5.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M13 18v6M10 21h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="27" cy="19" r="1.2" fill="currentColor"/><circle cx="30" cy="22" r="1.2" fill="currentColor"/>',
            'generic' => '<rect x="8" y="8" width="24" height="24" rx="4" stroke="currentColor" stroke-width="1.6"/><path d="M14 16h12M14 20h12M14 24h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
            default => '<rect x="7" y="7" width="26" height="20" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 32h33L33 27H7l-3.5 5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        };
        ?>
        <div class="request-card__device-mark" aria-hidden="true">
            <svg viewBox="0 0 40 40" fill="none"><?= $_requestCardIconSvg ?></svg>
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
