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
                $actionLabel = '';
                $actionHref = '';
                require dirname(__DIR__, 2) . '/components/empty-state.php';
                ?>
            <?php endif; ?>
        </section>

        <section class="account-security" data-reveal>
            <div class="account-security__intro">
                <span class="eyebrow">Безопасность</span>
                <h2>Смена пароля.</h2>
                <p>Новый пароль — не короче 8 символов, хотя бы одна буква и одна цифра. После смены пароля войдите с ним заново.</p>
            </div>
            <form class="account-security__form" method="post" action="/account/password">
                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">
                <label class="field<?= isset($errors['current_password']) ? ' field--invalid' : '' ?>" for="account-current-password">
                    <span class="field__label">Текущий пароль</span>
                    <input id="account-current-password" type="password" name="current_password" autocomplete="current-password" required<?= isset($errors['current_password']) ? ' aria-invalid="true" aria-describedby="account-current-password-error"' : '' ?>>
                    <?php if (isset($errors['current_password'])): ?><small class="field__error" id="account-current-password-error"><?= e((string) $errors['current_password']) ?></small><?php endif; ?>
                </label>
                <label class="field<?= isset($errors['password']) ? ' field--invalid' : '' ?>" for="account-new-password">
                    <span class="field__label">Новый пароль</span>
                    <input id="account-new-password" type="password" name="password" autocomplete="new-password" required<?= isset($errors['password']) ? ' aria-invalid="true" aria-describedby="account-new-password-error"' : '' ?>>
                    <?php if (isset($errors['password'])): ?><small class="field__error" id="account-new-password-error"><?= e((string) $errors['password']) ?></small><?php endif; ?>
                </label>
                <label class="field<?= isset($errors['password_confirmation']) ? ' field--invalid' : '' ?>" for="account-password-confirmation">
                    <span class="field__label">Повторите новый пароль</span>
                    <input id="account-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" required<?= isset($errors['password_confirmation']) ? ' aria-invalid="true" aria-describedby="account-password-confirmation-error"' : '' ?>>
                    <?php if (isset($errors['password_confirmation'])): ?><small class="field__error" id="account-password-confirmation-error"><?= e((string) $errors['password_confirmation']) ?></small><?php endif; ?>
                </label>
                <div class="account-security__actions">
                    <button class="button button--primary" type="submit">Изменить пароль</button>
                </div>
            </form>
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
