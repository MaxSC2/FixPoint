<?php
declare(strict_types=1);

$pageTitle = 'Ремонт техники с понятным процессом';
$activeNav = 'home';
require dirname(__DIR__, 2) . '/layouts/header.php';

$featuredServices = isset($featuredServices) && is_array($featuredServices) ? $featuredServices : [];
?>
<section class="hero">
    <div class="container hero__grid">
        <div class="hero__content">
            <span class="eyebrow"><span class="eyebrow__dot"></span> Сервисный центр FixPoint</span>
            <h1>Ремонт техники<br><span>без лишней неопределённости.</span></h1>
            <p class="hero__lead">Оставьте заявку, получите результат диагностики и следите за каждым этапом ремонта в личном кабинете.</p>
            <div class="hero__actions">
                <a class="button button--primary button--large" href="/register">Оформить заявку <span aria-hidden="true">→</span></a>
                <a class="button button--secondary button--large" href="/services">Посмотреть услуги</a>
            </div>
            <div class="hero__note">
                <span class="hero__note-icon" aria-hidden="true">✓</span>
                <span>Стоимость согласовывается до начала ремонтных работ.</span>
            </div>
        </div>

        <div class="hero-visual" aria-label="Иллюстрация процесса обслуживания">
            <div class="hero-visual__glow"></div>
            <div class="device-card device-card--back">
                <div class="device-card__top"><span></span><span></span><span></span></div>
                <div class="device-card__screen">
                    <div class="device-screen__line device-screen__line--wide"></div>
                    <div class="device-screen__line"></div>
                    <div class="device-screen__chart"><span></span><span></span><span></span><span></span><span></span><span></span></div>
                </div>
                <div class="device-card__base"></div>
            </div>
            <div class="repair-float repair-float--status">
                <span class="repair-float__check" aria-hidden="true">✓</span>
                <span><strong>Заявка в работе</strong><small>Статус обновлён</small></span>
            </div>
            <div class="repair-float repair-float--time">
                <span class="repair-float__clock" aria-hidden="true">◷</span>
                <span><strong>Всё по этапам</strong><small>История ремонта доступна</small></span>
            </div>
            <span class="hero-visual__caption">От обращения до результата</span>
        </div>
    </div>
</section>

<section class="trust-strip" aria-label="Преимущества сервиса">
    <div class="container trust-strip__grid">
        <div><span class="trust-strip__number">01</span><span><strong>Прозрачный процесс</strong><small>Понятные этапы ремонта</small></span></div>
        <div><span class="trust-strip__number">02</span><span><strong>Согласование стоимости</strong><small>До начала работ</small></span></div>
        <div><span class="trust-strip__number">03</span><span><strong>История заявки</strong><small>Вся информация в кабинете</small></span></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Что мы обслуживаем</span>
                <h2>Техника, которой<br>можно вернуть рабочее состояние</h2>
            </div>
            <a class="text-link section-heading__link" href="/services">Все услуги <span aria-hidden="true">→</span></a>
        </div>
        <div class="category-grid">
            <a class="category-card" href="/services?q=ноутбук">
                <span class="category-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none"><rect x="7" y="7" width="26" height="20" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 32h33L33 27H7l-3.5 5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                </span>
                <strong>Ноутбуки</strong><small>Диагностика, обслуживание и ремонт</small><span class="category-card__arrow" aria-hidden="true">↗</span>
            </a>
            <a class="category-card" href="/services?q=компьютер">
                <span class="category-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none"><rect x="5" y="6" width="30" height="22" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M20 28v6m-7 0h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <strong>Компьютеры</strong><small>Сборка, настройка и восстановление</small><span class="category-card__arrow" aria-hidden="true">↗</span>
            </a>
            <a class="category-card" href="/services?q=смартфон">
                <span class="category-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none"><rect x="11" y="3.5" width="18" height="33" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M17 8h6m-3 23h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </span>
                <strong>Смартфоны</strong><small>Проверка и ремонт мобильных устройств</small><span class="category-card__arrow" aria-hidden="true">↗</span>
            </a>
            <a class="category-card" href="/services?q=периферия">
                <span class="category-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none"><rect x="5" y="7" width="30" height="26" rx="5" stroke="currentColor" stroke-width="1.8"/><path d="M10 14h2m4 0h2m4 0h2m4 0h2M10 20h2m4 0h2m4 0h2m4 0h2m-14 6h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <strong>Периферия</strong><small>Настройка и диагностика аксессуаров</small><span class="category-card__arrow" aria-hidden="true">↗</span>
            </a>
        </div>
    </div>
</section>

<section class="section section--soft">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Как это работает</span>
                <h2>Три понятных шага</h2>
            </div>
            <p class="section-heading__description">Вы всегда понимаете, что происходит с устройством и какое действие требуется дальше.</p>
        </div>
        <div class="steps-grid">
            <article class="step-card"><span class="step-card__number">01</span><h3>Оставьте заявку</h3><p>Опишите устройство и проблему. Заявка появится в вашем личном кабинете.</p></article>
            <article class="step-card"><span class="step-card__number">02</span><h3>Дождитесь диагностики</h3><p>Мастер проверит устройство, зафиксирует результат и предложит смету.</p></article>
            <article class="step-card"><span class="step-card__number">03</span><h3>Согласуйте ремонт</h3><p>После вашего решения мастер приступит к работе. Статус будет обновляться в заявке.</p></article>
        </div>
    </div>
</section>

<?php if ($featuredServices !== []): ?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div><span class="eyebrow">Каталог</span><h2>Популярные услуги</h2></div>
            <a class="text-link" href="/services">Весь каталог <span aria-hidden="true">→</span></a>
        </div>
        <div class="service-grid">
            <?php foreach (array_slice($featuredServices, 0, 3) as $service): ?>
                <?php require dirname(__DIR__, 2) . '/components/service-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="final-cta">
    <div class="container final-cta__inner">
        <div><span class="eyebrow eyebrow--light">Начните с диагностики</span><h2>Расскажите, что случилось<br>с вашим устройством.</h2><p>Создайте аккаунт, чтобы сохранить заявку и отслеживать её статус.</p></div>
        <a class="button button--light button--large" href="/register">Создать заявку <span aria-hidden="true">→</span></a>
    </div>
</section>

<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
