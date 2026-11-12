<?php
declare(strict_types=1);

$pageTitle = 'Личный кабинет';
$activeNav = 'requests';
$authUser = isset($authUser) && is_array($authUser) ? $authUser : [];
$recentRequests = isset($recentRequests) && is_array($recentRequests) ? array_values(array_filter($recentRequests, 'is_array')) : [];
$userName = trim((string) ($authUser['full_name'] ?? ''));
$nameParts = $userName !== '' ? (preg_split('/\\s+/u', $userName) ?: []) : [];
$firstName = (string) ($nameParts[0] ?? 'клиент');
require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="account-welcome" data-reveal>
            <div class="account-welcome__copy">
                <span class="eyebrow">Личный кабинет / FixPoint</span>
                <h1>Здравствуйте, <em><?= e($firstName) ?>.</em></h1>
                <p>Здесь собраны ваши обращения, результаты диагностики и решения по смете. Выберите заявку, чтобы продолжить.</p>
            </div>
            <a class="button button--primary button--large" href="/requests/create">Создать заявку <span aria-hidden="true">↗</span></a>
            <span class="account-welcome__seal" aria-hidden="true">FP<br>CLIENT</span>
        </div>

        <nav class="workspace-nav" aria-label="Разделы личного кабинета" data-reveal>
            <a class="workspace-nav__link is-active" href="/account" aria-current="page">Обзор</a>
            <a class="workspace-nav__link" href="/requests">Мои заявки</a>
            <a class="workspace-nav__link workspace-nav__link--action" href="/requests/create">Создать обращение <span aria-hidden="true">+</span></a>
        </nav>

        <section class="account-recent" data-reveal>
            <div class="account-recent__head">
                <div><span class="eyebrow">Ваши обращения</span><h2>Последние заявки</h2></div>
                <a class="text-link" href="/requests">Все заявки <span aria-hidden="true">↗</span></a>
            </div>
            <?php if ($recentRequests !== []): ?>
                <div class="request-card-list">
                    <?php foreach (array_slice($recentRequests, 0, 4) as $request): ?>
                        <?php require dirname(__DIR__, 2) . '/components/request-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php
                $title = 'Заявок пока нет';
                $description = 'Когда вы отправите обращение, его номер, статус и дальнейшие события появятся в этом разделе.';
                $actionLabel = 'Создать первую заявку';
                $actionHref = '/requests/create';
                require dirname(__DIR__, 2) . '/components/empty-state.php';
                ?>
            <?php endif; ?>
        </section>

        <section class="account-guide" data-reveal>
            <div class="account-guide__intro">
                <span class="eyebrow">Как устроен процесс</span>
                <h2>Никаких решений вслепую.</h2>
                <p>После приёма устройства мастер проведёт диагностику. До начала ремонта вы увидите заключение и предложенную стоимость.</p>
            </div>
            <div class="account-guide__steps">
                <div><span>01</span><strong>Заявка</strong><small>Устройство и симптомы</small></div>
                <div><span>02</span><strong>Диагностика</strong><small>Результат проверки</small></div>
                <div><span>03</span><strong>Ваше решение</strong><small>Согласование сметы</small></div>
            </div>
        </section>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
