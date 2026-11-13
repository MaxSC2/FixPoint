<?php
declare(strict_types=1);

/**
 * @var array<string, mixed> $old
 * @var array<string, string> $errors
 */
$pageTitle = 'Регистрация';
require dirname(__DIR__, 2) . '/layouts/header.php';
?>
<section class="auth-shell">
    <div class="container auth-shell__grid">
        <div class="auth-intro">
            <span class="eyebrow"><span class="eyebrow__dot"></span> Новый клиент</span>
            <h1>Создайте аккаунт<br><span>и оформите заявку.</span></h1>
            <p class="auth-intro__lead">
                Аккаунт нужен, чтобы заявка была привязана к вам: статус, комментарии
                и согласование стоимости доступны только владельцу заявки.
            </p>
            <ul class="auth-points">
                <li>Заявка и её история хранятся в личном кабинете.</li>
                <li>Стоимость согласуется до начала ремонта.</li>
                <li>Внутренние заметки мастеров клиенту не показываются.</li>
            </ul>
        </div>

        <div class="auth-card">
            <h2>Регистрация</h2>
            <p class="auth-card__lead">Заполните данные — их можно будет уточнить в заявке.</p>

            <?php if (isset($errors['general']) && $errors['general'] !== ''): ?>
                <div class="alert alert--error" role="alert">
                    <span class="alert__marker" aria-hidden="true"></span>
                    <p><?= e($errors['general']) ?></p>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="/register" novalidate>
                <?= csrf_field() ?>

                <div class="form-grid">
                    <div class="form-grid__full">
                        <?php
                        $name = 'full_name';
                        $label = 'Имя и фамилия';
                        $type = 'text';
                        $value = (string) old_value($old, 'full_name', '');
                        $placeholder = 'Как к вам обращаться';
                        $help = '';
                        $error = $errors['full_name'] ?? '';
                        $required = true;
                        $autocomplete = 'name';
                        $multiline = false;
                        require dirname(__DIR__, 2) . '/components/form-field.php';
                        ?>
                    </div>
                    <div>
                        <?php
                        $name = 'email';
                        $label = 'Email';
                        $type = 'email';
                        $value = (string) old_value($old, 'email', '');
                        $placeholder = 'name@example.com';
                        $help = 'Используется для входа.';
                        $error = $errors['email'] ?? '';
                        $required = true;
                        $autocomplete = 'email';
                        $multiline = false;
                        require dirname(__DIR__, 2) . '/components/form-field.php';
                        ?>
                    </div>
                    <div>
                        <?php
                        $name = 'phone';
                        $label = 'Телефон';
                        $type = 'tel';
                        $value = (string) old_value($old, 'phone', '');
                        $placeholder = '+7 700 000 00 00';
                        $help = 'Необязательно.';
                        $error = $errors['phone'] ?? '';
                        $required = false;
                        $autocomplete = 'tel';
                        $multiline = false;
                        require dirname(__DIR__, 2) . '/components/form-field.php';
                        ?>
                    </div>
                    <div>
                        <?php
                        $name = 'password';
                        $label = 'Пароль';
                        $type = 'password';
                        $value = '';
                        $placeholder = '';
                        $help = 'Не менее 8 символов, хотя бы одна буква и цифра.';
                        $error = $errors['password'] ?? '';
                        $required = true;
                        $autocomplete = 'new-password';
                        $multiline = false;
                        require dirname(__DIR__, 2) . '/components/form-field.php';
                        ?>
                    </div>
                    <div>
                        <?php
                        $name = 'password_confirmation';
                        $label = 'Повторите пароль';
                        $type = 'password';
                        $value = '';
                        $placeholder = '';
                        $help = '';
                        $error = $errors['password_confirmation'] ?? '';
                        $required = true;
                        $autocomplete = 'new-password';
                        $multiline = false;
                        require dirname(__DIR__, 2) . '/components/form-field.php';
                        ?>
                    </div>
                </div>

                <button class="button button--primary" type="submit">Создать аккаунт</button>
            </form>

            <p class="auth-switch">
                Уже есть аккаунт? <a href="/login">Войти</a>
            </p>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>