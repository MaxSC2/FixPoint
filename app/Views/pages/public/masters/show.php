<?php
declare(strict_types=1);

$pageTitle = (string) ($profile['full_name'] ?? 'Профиль мастера');
$activeNav = 'masters';
$profile = isset($profile) && is_array($profile) ? $profile : [];
$reviews = isset($reviews) && is_array($reviews) ? $reviews : [];
$works = isset($works) && is_array($works) ? $works : [];
$name = (string) ($profile['full_name'] ?? 'Мастер FixPoint');
$parts = preg_split('/\s+/u', trim($name)) ?: [];
$initials = '';
foreach (array_slice($parts, 0, 2) as $part) { $initials .= mb_substr($part, 0, 1); }
$reviewCount = (int) ($profile['review_count'] ?? 0);
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/">Главная</a><span aria-hidden="true">/</span><a href="/masters">Мастера</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($name) ?></span></nav>
        <section class="master-profile-hero" data-reveal>
            <div class="master-avatar master-avatar--large" aria-hidden="true">
                <?php if (!empty($profile['avatar_storage_key'])): ?>
                    <img class="master-avatar__image" src="/masters/<?= e((string) $profile['technician_id']) ?>/avatar" alt="">
                <?php else: ?>
                    <?= e(mb_strtoupper($initials !== '' ? $initials : 'FP')) ?>
                <?php endif; ?>
            </div>
            <div class="master-profile-hero__main">
                <span class="eyebrow">Публичный профиль специалиста</span>
                <h1><?= e($name) ?></h1>
                <p class="master-card__headline"><?= e((string) ($profile['headline'] ?? 'Специалист FixPoint')) ?></p>
                <p><?= e((string) ($profile['bio'] ?? 'Описание специалиста пока не добавлено.')) ?></p>
                <p class="master-card__specialties"><?= e(trim((string) ($profile['specialties'] ?? '')) !== '' ? (string) $profile['specialties'] : 'Специализация не указана') ?></p>
            </div>
            <div class="master-profile-score">
                <?php if ($reviewCount > 0): ?><strong>★ <?= e(number_format((float) $profile['average_rating'], 1, '.', '')) ?></strong><span><?= e((string) $reviewCount) ?> опубликованных отзывов</span>
                <?php else: ?><strong>—</strong><span>Пока без оценок</span><?php endif; ?>
                <span><?= e((string) ($profile['completed_count'] ?? 0)) ?> завершённых заявок</span>
            </div>
        </section>

        <div class="account-recent__head"><div><span class="eyebrow">Портфолио</span><h2>Выполненные работы</h2></div><span class="guide-card__meta"><?= e((string) count($works)) ?> работ</span></div>
        <?php if ($works !== []): ?>
            <div class="master-work-list" data-reveal>
                <?php foreach ($works as $work): ?>
                    <?php
                    $deviceTitle = trim(implode(' ', array_filter([
                        (string) ($work['device_brand'] ?? ''),
                        (string) ($work['device_model'] ?? ''),
                    ])));
                    if ($deviceTitle === '') {
                        $deviceTitle = (string) ($work['device_type'] ?? 'Устройство');
                    }
                    $serviceName = trim((string) ($work['service_name'] ?? ''));
                    if ($serviceName === '') {
                        $serviceName = 'Диагностика и ремонт';
                    }
                    $completedAt = (string) ($work['completed_at'] ?? '');
                    $rating = $work['review_rating'] ?? null;
                    $cost = $work['final_cost'] ?? null;
                    ?>
                    <article class="master-work">
                        <div class="master-work__head">
                            <h3><?= e($deviceTitle) ?></h3>
                            <span class="master-work__number"><?= e((string) ($work['public_number'] ?? '')) ?></span>
                        </div>
                        <p class="master-work__service"><?= e($serviceName) ?></p>
                        <?php if (is_array($work['trail'] ?? null) && $work['trail'] !== []): ?>
                            <ol class="master-work__trail">
                                <?php foreach ($work['trail'] as $stage): ?>
                                    <?php
                                    $stageStatus = (string) ($stage['status'] ?? '');
                                    $stageDate = (string) ($stage['created_at'] ?? '');
                                    ?>
                                    <li class="master-work__stage">
                                        <span class="master-work__stage-dot" aria-hidden="true"></span>
                                        <span class="master-work__stage-label"><?= e(status_label($stageStatus)) ?></span>
                                        <?php if ($stageDate !== ''): ?>
                                            <time><?= e((new DateTimeImmutable($stageDate))->format('d.m.Y')) ?></time>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                        <div class="master-work__meta">
                            <?php if ($completedAt !== ''): ?>
                                <span>Завершено: <?= e((new DateTimeImmutable($completedAt))->format('d.m.Y')) ?></span>
                            <?php endif; ?>
                            <?php if ($rating !== null && $rating !== ''): ?>
                                <span class="master-work__rating">★ <?= e(number_format((float) $rating, 1, '.', '')) ?>/5</span>
                            <?php endif; ?>
                            <?php if ($cost !== null && $cost !== ''): ?>
                                <span><?= e(number_format((float) $cost, 0, '.', ' ')) ?> ₸</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><h2>Работы пока не опубликованы</h2><p>Здесь появятся завершённые заказы мастера с историей работ.</p></div>
        <?php endif; ?>

        <div class="account-recent__head"><div><span class="eyebrow">Обратная связь</span><h2>Отзывы клиентов</h2></div></div>
        <?php if ($reviews !== []): ?>
            <div class="master-review-list" data-reveal>
                <?php foreach ($reviews as $review): ?>
                    <article class="master-review">
                        <div class="master-review__head"><strong><?= e((string) ($review['customer_first_name'] ?? 'Клиент')) ?></strong><span>★ <?= e((string) $review['rating']) ?>/5</span><time><?= e((new DateTimeImmutable((string) $review['created_at']))->format('d.m.Y')) ?></time></div>
                        <?php if (!empty($review['review_text'])): ?><p><?= nl2br(e((string) $review['review_text'])) ?></p><?php else: ?><p class="master-review__empty">Клиент оставил оценку без комментария.</p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><h2>Отзывы появятся после завершённых ремонтов</h2><p>Оценки формируются только по реальным заявкам клиентов.</p></div>
        <?php endif; ?>
        <div class="request-form__actions"><a class="button button--ghost" href="/masters">К списку мастеров</a><a class="button button--primary" href="/requests/create">Создать заявку</a></div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
