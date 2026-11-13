<?php
declare(strict_types=1);

$pageTitle = 'Вход в личный кабинет';
$activeNav = '';
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$email = (string) ($old['email'] ?? '');
$emailError = (string) ($errors['email'] ?? '');
$passwordError = (string) ($errors['password'] ?? '');
$generalError = (string) ($errors['general'] ?? '');
$returnTo = isset($returnTo) && is_string($returnTo) ? $returnTo : null;
require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="auth-page">
    <div class="container auth-page__grid">
        <div class="auth-story" data-reveal>
            <span class="eyebrow">Личный кабинет / FixPoint</span>
            <h1>Продолжим<br><em>с того места,</em><br>где остановились.</h1>
            <p>Войдите, чтобы посмотреть ход ремонта, прочитать заключение мастера или принять решение по смете.</p>
            <div class="auth-story__steps">
                <div><span>01</span><strong>Статус заявки</strong><small>Текущий этап и история событий</small></div>
                <div><span>02</span><strong>Диагностика</strong><small>Что удалось обнаружить мастеру</small></div>
                <div><span>03</span><strong>Ваше решение</strong><small>Смета до начала ремонтных работ</small></div>
            </div>
            <span class="auth-story__signature">ПОНЯТНЫЙ ПРОЦЕСС · FIXPOINT</span>
        </div>

        <div class="auth-panel" data-reveal>
            <div class="auth-panel__head">
                <span class="auth-panel__overline">РАБОЧЕЕ ПРОСТРАНСТВО</span>
                <h2>С возвращением</h2>
                <p>Введите данные, указанные при регистрации.</p>
            </div>

            <?php if ($generalError !== ''): ?>
                <div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e($generalError) ?></p></div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="/login">
                <?php if ($returnTo !== null): ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><?php endif; ?>
                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">

                <label class="field<?= $emailError !== '' ? ' field--invalid' : '' ?>" for="login-email">
                    <span class="field__label">Электронная почта</span>
                    <input
                        id="login-email"
                        type="email"
                        name="email"
                        value="<?= e($email) ?>"
                        autocomplete="username"
                        inputmode="email"
                        placeholder="name@example.com"
                        required
                        <?= $emailError !== '' ? 'aria-invalid="true" aria-describedby="login-email-error"' : '' ?>
                    >
                    <?php if ($emailError !== ''): ?><small class="field__error" id="login-email-error"><?= e($emailError) ?></small><?php endif; ?>
                </label>

                <div class="field<?= $passwordError !== '' ? ' field--invalid' : '' ?>">
                    <label class="field__label" for="login-password">Пароль</label>
                    <span class="auth-password">
                        <input
                            id="login-password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="Введите пароль"
                            required
                            <?= $passwordError !== '' ? 'aria-invalid="true" aria-describedby="login-password-error"' : '' ?>
                        >
                        <button class="auth-password__toggle" type="button" data-password-toggle aria-controls="login-password" aria-pressed="false" aria-label="Показать пароль">
                            <span data-password-toggle-label>Показать</span>
                        </button>
                    </span>
                    <?php if ($passwordError !== ''): ?><small class="field__error" id="login-password-error"><?= e($passwordError) ?></small><?php endif; ?>
                </div>

                <button class="button button--primary button--large auth-form__submit" type="submit">Войти в кабинет <span aria-hidden="true">↗</span></button>
            </form>

            <div class="auth-panel__foot">
                <span>Впервые в FixPoint?</span>
                <a href="/register">Создать аккаунт <span aria-hidden="true">↗</span></a>
            </div>
            <p class="auth-panel__privacy">Используйте личную учётную запись. Не передавайте пароль другим людям.</p>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
