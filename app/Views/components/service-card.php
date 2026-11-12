<?php
declare(strict_types=1);

/** @var array<string, mixed> $service */
$service = isset($service) && is_array($service) ? $service : [];
$serviceName = (string) ($service['name'] ?? 'Услуга');
$serviceSlug = (string) ($service['slug'] ?? '');
$serviceDescription = (string) ($service['short_description'] ?? '');
$priceFrom = $service['price_from'] ?? null;
$hasPrice = is_numeric($priceFrom) && (float) $priceFrom > 0;
$serviceUrl = $serviceSlug !== '' ? '/services/' . rawurlencode($serviceSlug) : '/services';
?>
<article class="service-card">
    <div class="service-card__icon" aria-hidden="true">
        <svg viewBox="0 0 32 32" fill="none">
            <rect x="4" y="5" width="24" height="17" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
            <path d="M2.5 26h27M12 22v4m8-4v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="m13 12 2.2 2.2L20 9.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="service-card__body">
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
        <a class="text-link" href="<?= e($serviceUrl) ?>">Подробнее <span aria-hidden="true">↗</span></a>
    </div>
</article>
