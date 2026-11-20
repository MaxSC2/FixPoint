<?php
declare(strict_types=1);

$pageTitle = (string) ($guide['title'] ?? 'Инструкция');
$activeNav = 'guides';
$guide = isset($guide) && is_array($guide) ? $guide : [];
$steps = isset($guide['steps']) && is_array($guide['steps']) ? $guide['steps'] : [];
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="guide-article">
            <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/">Главная</a><span aria-hidden="true">/</span><a href="/guides">Частые неисправности</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($guide['title'] ?? '') ?></span></nav>
            <div class="workspace-heading workspace-heading--compact">
                <div>
                    <span class="eyebrow"><?= e($guide['category_label'] ?? '') ?> / <?= e($guide['device_type'] ?? '') ?></span>
                    <h1><?= e($guide['title'] ?? '') ?></h1>
                    <p><?= e($guide['summary'] ?? '') ?></p>
                </div>
            </div>

            <section class="guide-article__section">
                <h2>Как проявляется проблема</h2>
                <p><?= nl2br(e($guide['symptoms'] ?? '')) ?></p>
            </section>
            <section class="guide-article__section">
                <h2>Возможные причины</h2>
                <p><?= nl2br(e($guide['possible_causes'] ?? '')) ?></p>
                <small class="field__help">Это распространённые варианты, а не подтверждённый диагноз конкретного устройства.</small>
            </section>
            <section class="guide-article__section">
                <h2>Что можно проверить самостоятельно</h2>
                <ol class="guide-steps">
                    <?php foreach ($steps as $step): ?><li><?= e($step) ?></li><?php endforeach; ?>
                </ol>
            </section>
            <section class="guide-article__section guide-safety">
                <h2>Чего делать не следует</h2>
                <p><?= nl2br(e($guide['do_not'] ?? '')) ?></p>
            </section>
            <section class="guide-article__section">
                <h2>Когда обратиться к мастеру</h2>
                <p><?= nl2br(e($guide['when_to_contact'] ?? '')) ?></p>
                <div class="request-form__actions">
                    <a class="button button--primary button--large" href="/requests/create">Создать заявку по этой проблеме <span aria-hidden="true">↗</span></a>
                    <a class="button button--ghost" href="/guides">Вернуться к статьям</a>
                </div>
            </section>
            <p class="guide-card__meta">Материал носит справочный характер. При сомнениях остановитесь на безопасных проверках и не разбирайте устройство.</p>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
