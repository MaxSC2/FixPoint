<?php
declare(strict_types=1);

$pageTitle = 'Контакты';
$activeNav = 'contacts';
require dirname(__DIR__, 2) . '/layouts/header.php';

$contactAddress = isset($contactAddress) ? (string) $contactAddress : '';
$contactPhone = isset($contactPhone) ? (string) $contactPhone : '';
$contactEmail = isset($contactEmail) ? (string) $contactEmail : '';
$workingHours = isset($workingHours) ? (string) $workingHours : '';
?>
<section class="page-hero page-hero--compact">
    <div class="container" data-reveal>
        <span class="eyebrow">Связь с FixPoint</span>
        <h1>Контакты сервисного центра</h1>
        <p>Здесь размещаются актуальные способы связи и информация о приёме устройств.</p>
        <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/">Главная</a><span aria-hidden="true">/</span><span aria-current="page">Контакты</span></nav>
    </div>
</section>

<section class="section">
    <div class="container contacts-layout" data-reveal>
        <div class="contacts-panel">
            <span class="eyebrow">Как нас найти</span>
            <h2>Информация для клиентов</h2>
            <div class="contact-row"><span class="contact-row__icon" aria-hidden="true">⌖</span><div><strong>Адрес</strong><p><?= $contactAddress !== '' ? e($contactAddress) : 'Адрес сервисного центра будет опубликован после настройки проекта.' ?></p></div></div>
            <div class="contact-row"><span class="contact-row__icon" aria-hidden="true">☎</span><div><strong>Телефон</strong><p><?= $contactPhone !== '' ? e($contactPhone) : 'Телефон пока не указан.' ?></p></div></div>
            <div class="contact-row"><span class="contact-row__icon" aria-hidden="true">@</span><div><strong>Электронная почта</strong><p><?= $contactEmail !== '' ? e($contactEmail) : 'Электронная почта пока не указана.' ?></p></div></div>
            <div class="contact-row"><span class="contact-row__icon" aria-hidden="true">◷</span><div><strong>График работы</strong><p><?= $workingHours !== '' ? e($workingHours) : 'График работы будет опубликован после настройки проекта.' ?></p></div></div>
        </div>
        <aside class="contacts-aside">
            <span class="contacts-aside__mark" aria-hidden="true">FP</span>
            <h2>Есть проблема с устройством?</h2>
            <p>Создайте заявку в личном кабинете. Так описание неисправности и дальнейшая история останутся в одном месте.</p>
            <a class="button button--light button--large" href="/register">Оформить заявку <span aria-hidden="true">→</span></a>
        </aside>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
