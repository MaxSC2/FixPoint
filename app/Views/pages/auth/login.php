<?php
declare(strict_types=1);

/**
 * @var array<string, mixed> $old
 * @var array<string, string> $errors
 * @var string $csrfToken
 * @var string|null $returnTo
 */
$pageTitle = 'Вход';
require dirname(__DIR__, 2) . '/layouts/header.php';

$loginAction = $returnTo !== null
    ? '/login?return_to=' . rawurlencode((string) $returnTo)
    : '/login';
?>
<section class="auth-shell">
    <div class="container auth-shell__grid">
        <div class="auth-intro">
            <span class="eyebrow"><span class="eyebrow__dot"></span> Личный кабинет</span>
            <h1>Следите за ремонтом<br><span>в одном месте.</span></h1>
            <p class="auth-intro__lead">
                После входа доступны ваши заявки, статус диагностики, согласование стоимости
                и история работы мастера.
            </p>
            <ul class="auth-points">
                <li>Только ваши заявки — доступ проверяется на сервере.</li>
                <li>Понятный статус без звонков и уточнений.</li>
                <li>История ремонта сохраняется в заявке.</li>
            </ul>
        </div>

        <div class="auth-card">
            <h2>Вход</h2>
            <p class="auth-card__lead">Введите email и пароль, указанные при регистрации.</p>

            <?php if (isset($errors['general']) && $errors['general'] !== ''): ?>
                <div class="alert alert--error" role="alert">
                    <span class="alert__marker" aria-hidden="true"></span>
                    <p><?= e($errors['general']) ?></p>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= e($loginAction) ?>" novalidate>
                <?= csrf_field() ?>

                <?php
                $name = 'email';
                $label = 'Email';
                $type = 'email';
                $value = (string) old_value($old, 'email', '');
                $placeholder = 'name@example.com';
                $help = '';
                $error = $errors['email'] ?? '';
                $required = true;
                $autocomplete = 'email';
                $multiline = false;
                require dirname(__DIR__, 2) . '/components/form-field.php';
                ?>

                <?php
                $name = 'password';
                $label = 'Пароль';
                $type = 'password';
                $value = '';
                $placeholder = '';
                $help = '';
                $error = $errors['password'] ?? '';
                $required = true;
                $autocomplete = 'current-password';
                $multiline = false;
                require dirname(__DIR__, 2) . '/components/form-field.php';
                ?>

                <button class="button button--primary" type="submit">Войти</button>
            </form>

            <p class="auth-switch">
                Нет аккаунта? <a href="/register">Зарегистрироваться</a>
            </p>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>