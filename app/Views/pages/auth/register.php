<?php
declare(strict_types=1);

$pageTitle = 'Регистрация';
$activeNav = '';
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$fullName = (string) ($old['full_name'] ?? '');
$email = (string) ($old['email'] ?? '');
$phone = (string) ($old['phone'] ?? '');
$generalError = (string) ($errors['general'] ?? '');
require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="auth-page auth-page--register">
    <div class="container auth-page__grid">
        <div class="auth-story" data-reveal>
            <span class="eyebrow">Новый аккаунт / FixPoint</span>
            <h1>Ваш ремонт.<br><em>Всё по делу.</em></h1>
            <p>Создайте личный кабинет, чтобы сохранить заявку и видеть её историю в одном месте.</p>
            <div class="auth-story__steps">
                <div><span>01</span><strong>Опишите устройство</strong><small>Модель и симптомы неисправности</small></div>
                <div><span>02</span><strong>Получите диагностику</strong><small>Результат проверки и смету</small></div>
                <div><span>03</span><strong>Решите, как поступить</strong><small>Согласуйте или отклоните предложенные работы</small></div>
            </div>
            <span class="auth-story__signature">ПОНЯТНЫЙ ПРОЦЕСС · FIXPOINT</span>
        </div>

        <div class="auth-panel" data-reveal>
            <div class="auth-panel__head">
                <span class="auth-panel__overline">ЛИЧНЫЙ КАБИНЕТ</span>
                <h2>Создать аккаунт</h2>
                <p>Заполните основные данные. Остальное можно будет указать в заявке.</p>
            </div>

            <?php if ($generalError !== ''): ?>
                <div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e($generalError) ?></p></div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="/register">
                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">

                <label class="field<?= isset($errors['full_name']) ? ' field--invalid' : '' ?>" for="register-name">
                    <span class="field__label">Имя и фамилия</span>
                    <input id="register-name" type="text" name="full_name" value="<?= e($fullName) ?>" autocomplete="name" maxlength="160" placeholder="Как к вам обращаться" required <?= isset($errors['full_name']) ? 'aria-invalid="true" aria-describedby="register-name-error"' : '' ?>>
                    <?php if (isset($errors['full_name'])): ?><small class="field__error" id="register-name-error"><?= e((string) $errors['full_name']) ?></small><?php endif; ?>
                </label>

                <label class="field<?= isset($errors['email']) ? ' field--invalid' : '' ?>" for="register-email">
                    <span class="field__label">Электронная почта</span>
                    <input id="register-email" type="email" name="email" value="<?= e($email) ?>" autocomplete="email" inputmode="email" maxlength="190" placeholder="name@example.com" required <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="register-email-error"' : '' ?>>
                    <?php if (isset($errors['email'])): ?><small class="field__error" id="register-email-error"><?= e((string) $errors['email']) ?></small><?php endif; ?>
                </label>

                <label class="field<?= isset($errors['phone']) ? ' field--invalid' : '' ?>" for="register-phone">
                    <span class="field__label">Телефон <span class="field__optional">необязательно</span></span>
                    <input id="register-phone" type="tel" name="phone" value="<?= e($phone) ?>" autocomplete="tel" inputmode="tel" maxlength="32" placeholder="+7 ___ ___ __ __" <?= isset($errors['phone']) ? 'aria-invalid="true" aria-describedby="register-phone-error"' : '' ?>>
                    <?php if (isset($errors['phone'])): ?><small class="field__error" id="register-phone-error"><?= e((string) $errors['phone']) ?></small><?php endif; ?>
                </label>

                <div class="field<?= isset($errors['password']) ? ' field--invalid' : '' ?>">
                    <label class="field__label" for="register-password">Пароль</label>
                    <span class="auth-password">
                        <input id="register-password" type="password" name="password" autocomplete="new-password" placeholder="Придумайте пароль" required <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="register-password-error"' : '' ?>>
                        <button class="auth-password__toggle" type="button" data-password-toggle aria-controls="register-password" aria-pressed="false" aria-label="Показать пароль"><span data-password-toggle-label>Показать</span></button>
                    </span>
                    <?php if (isset($errors['password'])): ?><small class="field__error" id="register-password-error"><?= e((string) $errors['password']) ?></small><?php else: ?><small class="field__help">Не используйте пароль от других сервисов.</small><?php endif; ?>
                </div>

                <label class="field<?= isset($errors['password_confirmation']) ? ' field--invalid' : '' ?>" for="register-password-confirmation">
                    <span class="field__label">Повторите пароль</span>
                    <input id="register-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Введите пароль ещё раз" required <?= isset($errors['password_confirmation']) ? 'aria-invalid="true" aria-describedby="register-confirmation-error"' : '' ?>>
                    <?php if (isset($errors['password_confirmation'])): ?><small class="field__error" id="register-confirmation-error"><?= e((string) $errors['password_confirmation']) ?></small><?php endif; ?>
                </label>

                <button class="button button--primary button--large auth-form__submit" type="submit">Зарегистрироваться <span aria-hidden="true">↗</span></button>
            </form>

            <div class="auth-panel__foot">
                <span>Уже есть аккаунт?</span>
                <a href="/login">Войти <span aria-hidden="true">↗</span></a>
            </div>
            <p class="auth-panel__privacy">Создавая аккаунт, вы получаете доступ к личной истории обращений и согласованию сметы.</p>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
