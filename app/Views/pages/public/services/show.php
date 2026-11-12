<?php
declare(strict_types=1);

$service = isset($service) && is_array($service) ? $service : [];
$serviceName = (string) ($service['name'] ?? 'Услуга');
$pageTitle = $serviceName;
$activeNav = 'services';
$serviceDescription = (string) ($service['description'] ?? '');
$shortDescription = (string) ($service['short_description'] ?? '');
$priceFrom = $service['price_from'] ?? null;
$estimatedMinutes = $service['estimated_minutes'] ?? null;
$categoryName = (string) ($service['category_name'] ?? '');
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="page-hero page-hero--compact">
    <div class="container" data-reveal>
        <span class="eyebrow"><?= $categoryName !== '' ? e($categoryName) : 'Услуги FixPoint' ?></span>
        <h1><?= e($serviceName) ?></h1>
        <p><?= e($shortDescription !== '' ? $shortDescription : 'Диагностика и ремонт с предварительным согласованием стоимости.') ?></p>
        <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/">Главная</a><span aria-hidden="true">/</span><a href="/services">Услуги</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($serviceName) ?></span></nav>
    </div>
</section>

<section class="section">
    <div class="container service-detail-layout">
        <article class="service-detail" data-reveal>
            <span class="eyebrow">Описание услуги</span>
            <h2>Что входит в обслуживание</h2>
            <?php if ($serviceDescription !== ''): ?>
                <div class="service-detail__description"><?= nl2br(e($serviceDescription)) ?></div>
            <?php else: ?>
                <p>Состав работ и возможность выполнения услуги уточняются мастером после осмотра устройства.</p>
            <?php endif; ?>
            <div class="service-detail__steps">
                <div><span>01</span><p><strong>Приём устройства</strong><small>Зафиксируем модель и описание проблемы.</small></p></div>
                <div><span>02</span><p><strong>Диагностика и смета</strong><small>Уточним объём работ и согласуем стоимость.</small></p></div>
                <div><span>03</span><p><strong>Выполнение работ</strong><small>Начнём ремонт после подтверждения сметы.</small></p></div>
            </div>
        </article>
        <aside class="service-detail-aside" data-reveal>
            <span class="eyebrow eyebrow--light">Условия</span>
            <h2>Ориентиры по услуге</h2>
            <div class="service-detail-aside__item"><small>Стоимость</small><strong><?php if (is_numeric($priceFrom) && (float) $priceFrom > 0): ?>от <?= e(number_format((float) $priceFrom, 0, ',', ' ')) ?> ₸<?php else: ?>После диагностики<?php endif; ?></strong></div>
            <div class="service-detail-aside__item"><small>Время оценки</small><strong><?= is_numeric($estimatedMinutes) && (int) $estimatedMinutes > 0 ? e((string) (int) $estimatedMinutes) . ' мин.' : 'Уточняется' ?></strong></div>
            <p>Указанная цена, если она есть, является ориентировочной. Итоговая смета подтверждается до начала ремонта.</p>
            <a class="button button--light button--large" href="/register">Оформить заявку <span aria-hidden="true">→</span></a>
        </aside>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
