<?php
declare(strict_types=1);

$pageTitle = 'О сервисе';
$activeNav = 'about';
$aboutContent = isset($aboutContent) && is_array($aboutContent) ? $aboutContent : [];
$headline = (string) ($aboutContent['title'] ?? 'Сервис, в котором понятно, что происходит.');
$lead = (string) ($aboutContent['lead'] ?? 'FixPoint объединяет приём обращений, диагностику, согласование стоимости и контроль выполнения ремонта в одном рабочем процессе.');
$sectionHeading = (string) ($aboutContent['section_heading'] ?? 'Каждый этап имеет своё место');
$sectionBody = (string) ($aboutContent['section_body'] ?? '');
$paragraphs = preg_split('/\R\s*\R/u', trim($sectionBody)) ?: [];
$paragraphs = array_values(array_filter($paragraphs, static fn (string $paragraph): bool => trim($paragraph) !== ''));
require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="page-hero">
    <div class="container page-hero__grid" data-reveal>
        <div>
            <span class="eyebrow">О FixPoint</span>
            <h1><?= e($headline) ?></h1>
            <p><?= e($lead) ?></p>
            <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/">Главная</a><span aria-hidden="true">/</span><span aria-current="page">О сервисе</span></nav>
        </div>
        <div class="about-visual" aria-hidden="true">
            <div class="about-visual__ring"></div><div class="about-visual__core"><span>FP</span><small>FIXPOINT</small></div>
            <span class="about-visual__label about-visual__label--one">Диагностика</span>
            <span class="about-visual__label about-visual__label--two">Согласование</span>
            <span class="about-visual__label about-visual__label--three">Результат</span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container about-intro" data-reveal>
        <div><span class="eyebrow">Наш подход</span><h2><?= e($sectionHeading) ?></h2></div>
        <div class="about-intro__copy">
            <?php foreach ($paragraphs as $paragraph): ?>
                <p><?= nl2br(e(trim($paragraph))) ?></p>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section--soft">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">Принципы работы</span><h2>На чём строится процесс</h2></div></div>
        <div class="principle-grid" data-reveal>
            <article class="principle-card"><span>01</span><h3>Сначала диагностика</h3><p>Причина неисправности и объём работ уточняются до согласования сметы.</p></article>
            <article class="principle-card"><span>02</span><h3>Стоимость не сюрприз</h3><p>Работы начинаются после того, как клиенту показали и согласовали стоимость.</p></article>
            <article class="principle-card"><span>03</span><h3>История сохраняется</h3><p>Статусы и важные события записываются в истории заявки.</p></article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container about-bottom" data-reveal>
        <div><span class="eyebrow">Начать просто</span><h2>Опишите проблему<br>и создайте заявку.</h2><p>Дальнейшие шаги будут доступны в личном кабинете.</p></div>
        <a class="button button--primary button--large" href="/register">Создать аккаунт <span aria-hidden="true">→</span></a>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
