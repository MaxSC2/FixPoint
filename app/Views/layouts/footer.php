<?php
declare(strict_types=1);
?>
</main>
<footer class="site-footer">
    <div class="container site-footer__top">
        <div class="site-footer__brand">
            <a class="brand brand--footer" href="/" aria-label="FixPoint — главная">
                <span class="brand__mark" aria-hidden="true">
                    <svg viewBox="0 0 36 36" fill="none">
                        <path d="M18 3.5 31.5 11v14L18 32.5 4.5 25V11L18 3.5Z" stroke="currentColor" stroke-width="2.5"/>
                        <path d="M12 18.5 16 22.5 24.5 13.5" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="brand__word">Fix<span>Point</span></span>
            </a>
            <p>Понятный процесс ремонта: от первой заявки до выдачи устройства.</p>
        </div>

        <div class="site-footer__column">
            <h2>Навигация</h2>
            <a href="/services">Услуги</a>
            <a href="/about">О сервисе</a>
            <a href="/contacts">Контакты</a>
        </div>

        <div class="site-footer__column">
            <h2>Клиентам</h2>
            <a href="/register">Создать аккаунт</a>
            <a href="/login">Войти в кабинет</a>
            <a href="/requests/create">Оставить заявку</a>
        </div>

        <div class="site-footer__column">
            <h2>Работа с заявкой</h2>
            <p>Статус, диагностика и согласование стоимости доступны в личном кабинете.</p>
        </div>
    </div>
    <div class="container site-footer__bottom">
        <span>© <?= e(date('Y')) ?> FixPoint</span>
        <span>Система управления ремонтом техники</span>
    </div>
</footer>
</body>
</html>
