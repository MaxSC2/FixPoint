<?php
declare(strict_types=1);

$pageTitle = 'Ремонт техники с понятным процессом';
$activeNav = 'home';
require dirname(__DIR__, 2) . '/layouts/header.php';

$featuredServices = isset($featuredServices) && is_array($featuredServices) ? $featuredServices : [];
?>
<section class="atelier-hero">
    <div class="container atelier-hero__grid">
        <div class="atelier-hero__copy" data-reveal>
            <span class="atelier-kicker"><span class="atelier-kicker__signal" aria-hidden="true"></span> Сервисный центр FixPoint · мастерская техники</span>
            <h1>Вернём технике <em>рабочий ритм.</em><span>А ремонт сделаем понятным.</span></h1>
            <p class="atelier-hero__lead">От первой заявки до выдачи устройства — вы видите, что уже сделано, что происходит сейчас и какое решение нужно принять дальше.</p>
            <div class="atelier-hero__actions">
                <a class="button button--primary button--large" href="/register">Создать заявку <span aria-hidden="true">↗</span></a>
                <a class="button button--secondary button--large" href="/services">Изучить услуги</a>
            </div>
            <div class="atelier-proof" aria-label="Принципы обслуживания">
                <span><b aria-hidden="true">01</b> Диагностика до сметы</span>
                <span><b aria-hidden="true">02</b> Согласование до ремонта</span>
            </div>
        </div>

        <div class="atelier-hero__visual" data-reveal>
            <div class="atelier-art-frame">
                <img
                    src="/assets/images/repair-dossier.svg"
                    alt=""
                    width="760"
                    height="590"
                    fetchpriority="high"
                >
                <span class="atelier-art-frame__corner atelier-art-frame__corner--tl" aria-hidden="true"></span>
                <span class="atelier-art-frame__corner atelier-art-frame__corner--br" aria-hidden="true"></span>
            </div>
            <div class="atelier-visual-note">
                <span class="atelier-visual-note__marker" aria-hidden="true">↗</span>
                <span><strong>Каждый шаг зафиксирован</strong><small>История ремонта остаётся в заявке</small></span>
            </div>
            <span class="atelier-visual-index" aria-hidden="true">Схема 01 / Устройство изнутри</span>
        </div>
    </div>
    <div class="container atelier-hero__bottom" data-reveal>
        <span>Не обещаем невозможного.</span>
        <span>Объясняем реальное состояние устройства.</span>
        <span>Согласуем стоимость до начала работ.</span>
    </div>
</section>

<section class="atelier-services section">
    <div class="container">
        <div class="atelier-section-head" data-reveal>
            <div>
                <span class="eyebrow">Направления обслуживания / 01</span>
                <h2>У каждой неисправности<br>есть своя отправная точка.</h2>
            </div>
            <p>Выберите тип устройства или найдите нужную работу в каталоге. Если не уверены, начните с описания проблемы.</p>
        </div>

        <div class="atelier-service-list" data-reveal>
            <a class="atelier-service-row" href="/services?q=ноутбук">
                <span class="atelier-service-row__number">01</span>
                <span class="atelier-service-row__icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none"><rect x="9" y="7" width="30" height="24" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M4.5 36h39L39 31H9l-4.5 5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M19 36v2h10v-2" stroke="currentColor" stroke-width="1.7"/></svg>
                </span>
                <span class="atelier-service-row__copy"><strong>Ноутбуки</strong><small>Диагностика, охлаждение, питание, экран и обслуживание</small></span>
                <span class="atelier-service-row__arrow" aria-hidden="true">↗</span>
            </a>
            <a class="atelier-service-row" href="/services?q=компьютер">
                <span class="atelier-service-row__number">02</span>
                <span class="atelier-service-row__icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none"><rect x="5" y="6" width="38" height="27" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M24 33v7m-9 0h18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M11 12h26" stroke="currentColor" stroke-width="1.2"/></svg>
                </span>
                <span class="atelier-service-row__copy"><strong>Компьютеры</strong><small>Сборка, апгрейд, настройка и восстановление системы</small></span>
                <span class="atelier-service-row__arrow" aria-hidden="true">↗</span>
            </a>
            <a class="atelier-service-row" href="/services?q=смартфон">
                <span class="atelier-service-row__number">03</span>
                <span class="atelier-service-row__icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none"><rect x="14" y="4" width="20" height="40" rx="4" stroke="currentColor" stroke-width="1.7"/><path d="M20 9h8m-4 29h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </span>
                <span class="atelier-service-row__copy"><strong>Смартфоны</strong><small>Проверка, настройка и диагностика мобильных устройств</small></span>
                <span class="atelier-service-row__arrow" aria-hidden="true">↗</span>
            </a>
            <a class="atelier-service-row" href="/services?q=периферия">
                <span class="atelier-service-row__number">04</span>
                <span class="atelier-service-row__icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none"><rect x="5" y="9" width="38" height="29" rx="6" stroke="currentColor" stroke-width="1.7"/><path d="M12 17h3m5 0h3m5 0h3m5 0h3M12 24h3m5 0h3m5 0h3m5 0h3M17 31h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </span>
                <span class="atelier-service-row__copy"><strong>Периферия</strong><small>Подключение, обслуживание и поиск причин неисправности</small></span>
                <span class="atelier-service-row__arrow" aria-hidden="true">↗</span>
            </a>
        </div>
        <div class="atelier-services__foot" data-reveal>
            <span>Нужна конкретная работа?</span>
            <a class="text-link" href="/services">Открыть полный каталог <span aria-hidden="true">↗</span></a>
        </div>
    </div>
</section>

<section class="atelier-process">
    <div class="container">
        <div class="atelier-process__head" data-reveal>
            <span class="eyebrow eyebrow--light">Порядок работы / 02</span>
            <h2>Сначала факты.<br><em>Потом решения.</em></h2>
            <p>Мы разделяем диагностику, согласование и ремонт, чтобы вы не принимали решения вслепую.</p>
        </div>
        <div class="atelier-process__steps" data-reveal>
            <article class="atelier-process-step">
                <span class="atelier-process-step__num">01</span>
                <span class="atelier-process-step__line" aria-hidden="true"></span>
                <h3>Заявка</h3>
                <p>Вы описываете устройство и симптомы. Информация сохраняется в карточке обращения.</p>
            </article>
            <article class="atelier-process-step">
                <span class="atelier-process-step__num">02</span>
                <span class="atelier-process-step__line" aria-hidden="true"></span>
                <h3>Диагностика</h3>
                <p>Мастер проверяет устройство и фиксирует, что удалось обнаружить.</p>
            </article>
            <article class="atelier-process-step">
                <span class="atelier-process-step__num">03</span>
                <span class="atelier-process-step__line" aria-hidden="true"></span>
                <h3>Согласование</h3>
                <p>Вы изучаете смету и принимаете решение. Работы начинаются после подтверждения.</p>
            </article>
            <article class="atelier-process-step">
                <span class="atelier-process-step__num">04</span>
                <span class="atelier-process-step__line" aria-hidden="true"></span>
                <h3>Результат</h3>
                <p>Статус обновляется, а история обслуживания остаётся доступной в заявке.</p>
            </article>
        </div>
        <div class="atelier-process__foot" data-reveal>
            <span class="atelier-process__foot-mark" aria-hidden="true">i</span>
            <p>Срок и стоимость зависят от состояния устройства и необходимых деталей. Мы не подменяем диагностику обещаниями.</p>
        </div>
    </div>
</section>

<section class="atelier-transparency section">
    <div class="container atelier-transparency__grid">
        <div class="atelier-transparency__copy" data-reveal>
            <span class="eyebrow">Личный кабинет / 03</span>
            <h2>Вместо догадок —<br><em>понятная история.</em></h2>
            <p>Карточка ремонта собирает важное в одном месте: текущий этап, результат диагностики, смету и события по заявке. Не нужно вспоминать, что вам сказали по телефону.</p>
            <a class="text-link" href="/register">Как клиенту начать работу <span aria-hidden="true">↗</span></a>
        </div>
        <div class="repair-record" data-reveal>
            <div class="repair-record__top">
                <span class="repair-record__label">Пример карточки ремонта</span>
                <span class="repair-record__code">FP / 0000</span>
            </div>
            <div class="repair-record__device">
                <span class="repair-record__device-mark" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none"><rect x="7" y="7" width="26" height="20" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 32h33L33 27H7l-3.5 5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                </span>
                <span><strong>Устройство клиента</strong><small>Модель и описание проблемы появятся в заявке</small></span>
            </div>
            <div class="repair-record__timeline">
                <div class="repair-record__event is-done"><span class="repair-record__pin" aria-hidden="true"></span><span><strong>Заявка принята</strong><small>Обращение зарегистрировано</small></span><b>01</b></div>
                <div class="repair-record__event is-current"><span class="repair-record__pin" aria-hidden="true"></span><span><strong>Диагностика</strong><small>Фиксируется состояние устройства</small></span><b>02</b></div>
                <div class="repair-record__event"><span class="repair-record__pin" aria-hidden="true"></span><span><strong>Согласование сметы</strong><small>Решение остаётся за клиентом</small></span><b>03</b></div>
            </div>
            <div class="repair-record__notice"><span aria-hidden="true">↳</span> Демонстрационная схема, не реальная заявка</div>
        </div>
    </div>
</section>

<?php if ($featuredServices !== []): ?>
<section class="atelier-featured section">
    <div class="container">
        <div class="atelier-section-head" data-reveal>
            <div><span class="eyebrow">Каталог / 04</span><h2>Популярные услуги</h2></div>
            <a class="text-link" href="/services">Все услуги <span aria-hidden="true">↗</span></a>
        </div>
        <div class="service-grid" data-reveal>
            <?php foreach (array_slice($featuredServices, 0, 3) as $service): ?>
                <?php require dirname(__DIR__, 2) . '/components/service-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="atelier-final">
    <div class="container atelier-final__inner" data-reveal>
        <div>
            <span class="eyebrow eyebrow--light">Следующий шаг / 05</span>
            <h2>Начните с того,<br><em>что уже известно.</em></h2>
            <p>Опишите симптомы устройства. Остальное станет понятнее после диагностики.</p>
        </div>
        <a class="button button--light button--large" href="/register">Оформить заявку <span aria-hidden="true">↗</span></a>
        <span class="atelier-final__stamp" aria-hidden="true">FIX<br>POINT</span>
    </div>
</section>

<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
