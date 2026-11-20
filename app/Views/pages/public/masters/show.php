<?php
declare(strict_types=1);

$pageTitle = (string) ($profile['full_name'] ?? 'Профиль мастера');
$activeNav = 'masters';
$profile = isset($profile) && is_array($profile) ? $profile : [];
$reviews = isset($reviews) && is_array($reviews) ? $reviews : [];
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
            <div class="master-avatar master-avatar--large" aria-hidden="true"><?= e(mb_strtoupper($initials !== '' ? $initials : 'FP')) ?></div>
            <div class="master-profile-hero__main">
                <span class="eyebrow">Публичный профиль специалиста</span>
                <h1><?= e($name) ?></h1>
                <p class="master-card__headline"><?= e((string) ($profile['headline'] ?? 'Специалист FixPoint')) ?></p>
                <p><?= e((string) ($profile['bio'] ?? 'Описание специалиста пока не добавлено.')) ?></p>
                <p class="master-card__specialties"><?= e((string) ($profile['specialties'] ?? 'Специализация не указана')) ?></p>
            </div>
            <div class="master-profile-score">
                <?php if ($reviewCount > 0): ?><strong>★ <?= e(number_format((float) $profile['average_rating'], 1, '.', '')) ?></strong><span><?= e((string) $reviewCount) ?> опубликованных отзывов</span>
                <?php else: ?><strong>—</strong><span>Пока без оценок</span><?php endif; ?>
                <span><?= e((string) ($profile['completed_count'] ?? 0)) ?> завершённых заявок</span>
            </div>
        </section>

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
