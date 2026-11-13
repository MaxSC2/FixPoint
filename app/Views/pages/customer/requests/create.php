<?php
declare(strict_types=1);

$pageTitle = 'Новая заявка';
$activeNav = 'requests';
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$services = isset($services) && is_array($services) ? $services : [];
$services = array_values(array_filter($services, 'is_array'));
$serviceId = (string) ($old['service_id'] ?? '');
$deviceType = (string) ($old['device_type'] ?? '');
$deviceBrand = (string) ($old['device_brand'] ?? '');
$deviceModel = (string) ($old['device_model'] ?? '');
$problem = (string) ($old['problem_description'] ?? '');
$contact = (string) ($old['customer_contact'] ?? '');
$generalError = (string) ($errors['general'] ?? '');
require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div>
                <span class="eyebrow">Новое обращение / FixPoint</span>
                <h1>Создать заявку</h1>
                <p>Опишите устройство и проблему. Мастер уточнит детали после приёма и диагностики.</p>
                <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/account">Личный кабинет</a><span aria-hidden="true">/</span><a href="/requests">Мои заявки</a><span aria-hidden="true">/</span><span aria-current="page">Новая заявка</span></nav>
            </div>
            <div class="workspace-step-note"><span>01</span><strong>Информация об устройстве</strong><small>Поля со звёздочкой обязательны</small></div>
        </div>

        <nav class="workspace-nav" aria-label="Разделы личного кабинета" data-reveal>
            <a class="workspace-nav__link" href="/account">Обзор</a>
            <a class="workspace-nav__link" href="/requests">Мои заявки</a>
            <a class="workspace-nav__link workspace-nav__link--action is-active" href="/requests/create" aria-current="page">Создать обращение <span aria-hidden="true">+</span></a>
        </nav>

        <div class="request-create-layout">
            <section class="request-form-panel" data-reveal>
                <div class="request-form-panel__head">
                    <span class="eyebrow">Форма обращения</span>
                    <h2>Что нужно проверить?</h2>
                    <p>Укажите известную информацию. Необязательно точно определять неисправность самостоятельно.</p>
                </div>

                <?php if ($generalError !== ''): ?>
                    <div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e($generalError) ?></p></div>
                <?php endif; ?>

                <form class="request-form" method="post" action="/requests/create">
                    <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">

                    <div class="request-form__section">
                        <div class="request-form__section-head"><span>01</span><div><h3>Устройство</h3><p>Тип и модель помогут мастеру подготовиться к приёму.</p></div></div>
                        <div class="form-grid">
                            <label class="field form-grid__full<?= isset($errors['device_type']) ? ' field--invalid' : '' ?>" for="request-device-type">
                                <span class="field__label">Тип устройства <span class="field__required" aria-hidden="true">*</span></span>
                                <input id="request-device-type" type="text" name="device_type" value="<?= e($deviceType) ?>" list="device-type-options" maxlength="100" placeholder="Например, ноутбук или смартфон" required <?= isset($errors['device_type']) ? 'aria-invalid="true" aria-describedby="request-device-type-error"' : '' ?>>
                                <datalist id="device-type-options">
                                    <option value="Ноутбук"></option>
                                    <option value="Настольный компьютер"></option>
                                    <option value="Смартфон"></option>
                                    <option value="Планшет"></option>
                                    <option value="Монитор"></option>
                                    <option value="Периферия"></option>
                                </datalist>
                                <?php if (isset($errors['device_type'])): ?><small class="field__error" id="request-device-type-error"><?= e((string) $errors['device_type']) ?></small><?php endif; ?>
                            </label>

                            <label class="field<?= isset($errors['device_brand']) ? ' field--invalid' : '' ?>" for="request-device-brand">
                                <span class="field__label">Производитель</span>
                                <input id="request-device-brand" type="text" name="device_brand" value="<?= e($deviceBrand) ?>" maxlength="100" placeholder="Например, ASUS" <?= isset($errors['device_brand']) ? 'aria-invalid="true" aria-describedby="request-device-brand-error"' : '' ?>>
                                <?php if (isset($errors['device_brand'])): ?><small class="field__error" id="request-device-brand-error"><?= e((string) $errors['device_brand']) ?></small><?php endif; ?>
                            </label>

                            <label class="field<?= isset($errors['device_model']) ? ' field--invalid' : '' ?>" for="request-device-model">
                                <span class="field__label">Модель</span>
                                <input id="request-device-model" type="text" name="device_model" value="<?= e($deviceModel) ?>" maxlength="160" placeholder="Например, TUF Gaming F15" <?= isset($errors['device_model']) ? 'aria-invalid="true" aria-describedby="request-device-model-error"' : '' ?>>
                                <?php if (isset($errors['device_model'])): ?><small class="field__error" id="request-device-model-error"><?= e((string) $errors['device_model']) ?></small><?php endif; ?>
                            </label>

                            <label class="field form-grid__full<?= isset($errors['service_id']) ? ' field--invalid' : '' ?>" for="request-service">
                                <span class="field__label">Интересующая услуга <span class="field__optional">необязательно</span></span>
                                <select id="request-service" name="service_id" <?= isset($errors['service_id']) ? 'aria-invalid="true" aria-describedby="request-service-error"' : '' ?>>
                                    <option value="">Не уверен / нужна диагностика</option>
                                    <?php foreach ($services as $service): ?>
                                        <?php
                                        $optionId = (string) ($service['id'] ?? '');
                                        $optionName = (string) ($service['name'] ?? '');
                                        if ($optionId === '' || $optionName === '') {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?= e($optionId) ?>" <?= $serviceId === $optionId ? 'selected' : '' ?>><?= e($optionName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['service_id'])): ?><small class="field__error" id="request-service-error"><?= e((string) $errors['service_id']) ?></small><?php endif; ?>
                                <small class="field__help">Выбор услуги не ограничивает диагностику: мастер сможет уточнить состав работ.</small>
                            </label>
                        </div>
                    </div>

                    <div class="request-form__section">
                        <div class="request-form__section-head"><span>02</span><div><h3>Описание проблемы</h3><p>Расскажите, что происходит и когда это началось.</p></div></div>
                        <label class="field<?= isset($errors['problem_description']) ? ' field--invalid' : '' ?>" for="request-problem">
                            <span class="field__label">Что случилось? <span class="field__required" aria-hidden="true">*</span></span>
                            <textarea id="request-problem" name="problem_description" rows="6" maxlength="5000" placeholder="Например: ноутбук сильно нагревается и выключается под нагрузкой. Когда заметили проблему, что уже пробовали сделать?" required <?= isset($errors['problem_description']) ? 'aria-invalid="true" aria-describedby="request-problem-error"' : '' ?>><?= e($problem) ?></textarea>
                            <?php if (isset($errors['problem_description'])): ?><small class="field__error" id="request-problem-error"><?= e((string) $errors['problem_description']) ?></small><?php else: ?><small class="field__help">Не нужно самостоятельно ставить диагноз. Опишите наблюдаемые симптомы.</small><?php endif; ?>
                        </label>
                    </div>

                    <div class="request-form__section">
                        <div class="request-form__section-head"><span>03</span><div><h3>Связь с вами</h3><p>Контакт нужен, чтобы уточнить детали при приёме устройства.</p></div></div>
                        <label class="field<?= isset($errors['customer_contact']) ? ' field--invalid' : '' ?>" for="request-contact">
                            <span class="field__label">Телефон или другой удобный контакт <span class="field__required" aria-hidden="true">*</span></span>
                            <input id="request-contact" type="text" name="customer_contact" value="<?= e($contact) ?>" maxlength="80" autocomplete="tel" placeholder="+7 ___ ___ __ __" required <?= isset($errors['customer_contact']) ? 'aria-invalid="true" aria-describedby="request-contact-error"' : '' ?>>
                            <?php if (isset($errors['customer_contact'])): ?><small class="field__error" id="request-contact-error"><?= e((string) $errors['customer_contact']) ?></small><?php endif; ?>
                        </label>
                    </div>

                    <div class="request-form__actions">
                        <a class="button button--secondary" href="/requests">Отмена</a>
                        <button class="button button--primary button--large" type="submit">Отправить заявку <span aria-hidden="true">↗</span></button>
                    </div>
                </form>
            </section>

            <aside class="request-create-aside" data-reveal>
                <span class="request-create-aside__mark" aria-hidden="true">i</span>
                <span class="eyebrow eyebrow--light">Что дальше</span>
                <h2>Диагностика прежде ремонта.</h2>
                <ol>
                    <li><span>01</span><div><strong>Регистрация обращения</strong><p>Заявка появится в списке ваших обращений.</p></div></li>
                    <li><span>02</span><div><strong>Осмотр устройства</strong><p>Мастер уточнит состояние и зафиксирует результат диагностики.</p></div></li>
                    <li><span>03</span><div><strong>Согласование сметы</strong><p>Вы увидите стоимость и сможете принять решение до начала работ.</p></div></li>
                </ol>
                <p class="request-create-aside__note">Не указывайте пароли от устройства, банковские данные и другую конфиденциальную информацию.</p>
            </aside>
        </div>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
