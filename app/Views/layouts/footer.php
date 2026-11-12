<?php
declare(strict_types=1);
?>
</main>
<footer class="site-footer">
    <div class="container site-footer__top">
        <div class="site-footer__brand" data-reveal>
            <a class="brand brand--footer" href="/" aria-label="FixPoint — главная">
                <span class="brand__mark" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none">
                        <path d="M20 3.5 35 12v16L20 36.5 5 28V12L20 3.5Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 20.5 17.2 25.5 28.5 14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="29.3" cy="10.8" r="2.2" fill="#D9ED74"/>
                    </svg>
                </span>
                <span class="brand__word">Fix<span>Point</span></span>
            </a>
            <p>Сервис техники, где ремонт не превращается в чёрный ящик. Каждый этап — в одной понятной истории.</p>
        </div>

        <nav class="site-footer__column" aria-label="Навигация" data-reveal>
            <h2>Разделы</h2>
            <a href="/">Главная</a>
            <a href="/services">Каталог услуг</a>
            <a href="/about">О сервисе</a>
            <a href="/contacts">Контакты</a>
        </nav>

        <div class="site-footer__column" data-reveal>
            <h2>Клиенту</h2>
            <a href="/register">Создать аккаунт</a>
            <a href="/login">Войти в кабинет</a>
            <a href="/register">Оставить заявку</a>
        </div>

        <div class="site-footer__column" data-reveal>
            <h2>Принцип FixPoint</h2>
            <p>Сначала диагностика. Затем смета и ваше решение. Только после согласования — ремонт.</p>
        </div>
    </div>
    <div class="container site-footer__bottom">
        <span>© <?= e(date('Y')) ?> FixPoint</span>
        <span>Понятный процесс. Зафиксированный результат.</span>
    </div>
</footer>
</body>
</html>
