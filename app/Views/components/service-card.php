<?php
declare(strict_types=1);

/** @var array<string, mixed> $service */
$service = isset($service) && is_array($service) ? $service : [];
$serviceName = (string) ($service['name'] ?? 'Услуга');
$serviceSlug = (string) ($service['slug'] ?? '');
$serviceDescription = (string) ($service['short_description'] ?? '');
$serviceCategory = trim((string) ($service['category_name'] ?? ''));
$categorySlug = (string) ($service['category_slug'] ?? ($service['category']['slug'] ?? ''));
$priceFrom = $service['price_from'] ?? null;
$hasPrice = is_numeric($priceFrom) && (float) $priceFrom > 0;
$serviceUrl = $serviceSlug !== '' ? '/services/' . rawurlencode($serviceSlug) : '/services';
$iconVariant = match ($categorySlug) {
    'computers-laptops' => 'computers',
    'smartphones-tablets' => 'smartphones',
    'peripherals' => 'peripherals',
    'network' => 'network',
    'diagnostics' => 'diagnostics',
    'software' => 'software',
    'storage' => 'storage',
    default => 'general',
};
?>
<a class="service-card" href="<?= e($serviceUrl) ?>" data-category-slug="<?= e($categorySlug) ?>">
    <div class="service-card__icon service-card__icon--<?= e($iconVariant) ?>" aria-hidden="true">
        <?php if ($iconVariant === 'computers'): ?>
            <svg viewBox="0 0 40 40" fill="none" focusable="false"><rect x="6" y="6" width="28" height="22" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M2.5 33h35L33 29H7l-4.5 4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M16 33v2h8v-2" stroke="currentColor" stroke-width="1.6"/></svg>
        <?php elseif ($iconVariant === 'smartphones'): ?>
            <svg viewBox="0 0 40 40" fill="none" focusable="false"><rect x="11" y="3.5" width="18" height="33" rx="4" stroke="currentColor" stroke-width="1.8"/><path d="M16 8h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="20" cy="31.5" r="1.4" fill="currentColor"/></svg>
        <?php elseif ($iconVariant === 'peripherals'): ?>
            <svg viewBox="0 0 40 40" fill="none" focusable="false"><rect x="3.5" y="9" width="33" height="22" rx="4" stroke="currentColor" stroke-width="1.8"/><path d="M8 15h3m4 0h3m4 0h3m4 0h3M8 20h3m4 0h3m4 0h3m4 0h3M12 25h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <?php elseif ($iconVariant === 'network'): ?>
            <svg viewBox="0 0 40 40" fill="none" focusable="false"><rect x="5" y="18" width="30" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M11 18 8 7m21 11 3-11M15 25h.01M21 25h.01M27 25h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M14 13a9 9 0 0 1 12 0M17 16a4.5 4.5 0 0 1 6 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        <?php elseif ($iconVariant === 'diagnostics'): ?>
            <svg viewBox="0 0 40 40" fill="none" focusable="false"><circle cx="17.5" cy="17.5" r="10.5" stroke="currentColor" stroke-width="1.8"/><path d="m25.5 25.5 8 8M17.5 12v11M12 17.5h11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <?php else: ?>
            <svg viewBox="0 0 40 40" fill="none" focusable="false"><rect x="9" y="9" width="22" height="22" rx="4" stroke="currentColor" stroke-width="1.8"/><path d="M15 3v6m10-6v6M15 31v6m10-6v6M3 15h6m-6 10h6m22-10h6m-6 10h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M15 20h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <?php endif; ?>
    </div>
    <div class="service-card__body">
        <?php if ($serviceCategory !== ''): ?><span class="service-card__category"><?= e($serviceCategory) ?></span><?php endif; ?>
        <h3><?= e($serviceName) ?></h3>
        <?php if ($serviceDescription !== ''): ?>
            <p><?= e($serviceDescription) ?></p>
        <?php else: ?>
            <p>Условия и стоимость уточняются после осмотра устройства.</p>
        <?php endif; ?>
    </div>
    <div class="service-card__bottom">
        <span class="service-card__price">
            <?php if ($hasPrice): ?>
                от <?= e(number_format((float) $priceFrom, 0, ',', ' ')) ?> ₸
            <?php else: ?>
                После диагностики
            <?php endif; ?>
        </span>
        <span class="service-card__action">Подробнее <span aria-hidden="true">↗</span></span>
    </div>
</a>
