<?php
declare(strict_types=1);

$pageTitle = 'Новая заявка — администрирование';
$activeNav = 'admin';
$adminActiveSection = 'requests';
$customers = isset($customers) && is_array($customers) ? array_values(array_filter($customers, 'is_array')) : [];
$services = isset($services) && is_array($services) ? array_values(array_filter($services, 'is_array')) : [];
$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];

$customerId = (string) ($old['customer_id'] ?? '');
$deviceType = (string) ($old['device_type'] ?? '');
$deviceBrand = (string) ($old['device_brand'] ?? '');
$deviceModel = (string) ($old['device_model'] ?? '');
$serviceId = (string) ($old['service_id'] ?? '');
$problem = (string) ($old['problem_description'] ?? '');
$contact = (string) ($old['customer_contact'] ?? '');
$generalError = (string) ($errors['general'] ?? '');

$serviceGroups = [];
foreach ($services as $service) {
    $optionId = (string) ($service['id'] ?? '');
    $optionName = (string) ($service['name'] ?? '');
    if ($optionId === '' || $optionName === '') {
        continue;
    }
    $categoryName = trim((string) ($service['category_name'] ?? ''));
    $serviceGroups[$categoryName === '' ? 'Без категории' : $categoryName][] = $service;
}

require dirname(__DIR__, 3) . '/layouts/header.php';
?>
<section class="workspace-page admin-workspace">
    <div class="container">
        <div class="workspace-heading workspace-heading--compact" data-reveal>
            <div>
                <nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="/admin">Панель администратора</a><span aria-hidden="true">/</span><a href="/admin/requests">Заявки</a><span aria-hidden="true">/</span><span aria-current="page">Новая заявка</span></nav>
                <span class="eyebrow">Реестр / Новое обращение</span>
                <h1>Создать заявку</h1>
                <p>Заявка будет создана от имени выбранного клиента и сразу появится в общем реестре.</p>
            </div>
            <a class="button button--ghost" href="/admin/requests">К реестру</a>
        </div>

        <?php require dirname(__DIR__, 3) . '/components/admin-nav.php'; ?>

        <section class="request-form-panel" data-reveal>
            <div class="request-form-panel__head">
                <span class="eyebrow">Данные обращения</span>
                <h2>Клиент и устройство</h2>
                <p>Заполните обязательные поля. Мастера можно назначить позже из реестра — это необязательный шаг.</p>
            </div>

            <?php if ($generalError !== ''): ?>
                <div class="alert alert--error" role="alert"><span class="alert__marker" aria-hidden="true"></span><p><?= e($generalError) ?></p></div>
            <?php endif; ?>

            <form class="request-form" method="post" action="/admin/requests/create">
                <input type="hidden" name="_token" value="<?= e($csrfToken ?? '') ?>">

                <div class="request-form__section">
                    <div class="request-form__section-head"><span>01</span><div><h3>Клиент</h3><p>Заявка будет привязана к выбранной учётной записи.</p></div></div>
                    <label class="field<?= isset($errors['customer_id']) ? ' field--invalid' : '' ?>" for="admin-request-customer">
                        <span class="field__label">Клиент <span class="field__required" aria-hidden="true">*</span></span>
                        <select id="admin-request-customer" name="customer_id" required <?= isset($errors['customer_id']) ? 'aria-invalid="true" aria-describedby="admin-request-customer-error"' : '' ?>>
                            <option value="">Выберите клиента</option>
                            <?php foreach ($customers as $customer): ?>
                                <?php
                                $optionId = (string) ($customer['id'] ?? '');
                                $optionName = (string) ($customer['full_name'] ?? '');
                                if ($optionId === '' || $optionName === '') {
                                    continue;
                                }
                                $optionEmail = (string) ($customer['email'] ?? '');
                                ?>
                                <option value="<?= e($optionId) ?>" data-email="<?= e($optionEmail) ?>" data-phone="<?= e((string) ($customer['phone'] ?? '')) ?>" <?= $customerId === $optionId ? 'selected' : '' ?>><?= e($optionName) ?><?= $optionEmail !== '' ? ' · ' . e($optionEmail) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['customer_id'])): ?><small class="field__error" id="admin-request-customer-error"><?= e((string) $errors['customer_id']) ?></small><?php endif; ?>
                        <small class="field__help">В списке только активные клиенты. Контакт можно уточнить в поле ниже.</small>
                    </label>
                </div>

                <div class="request-form__section">
                    <div class="request-form__section-head"><span>02</span><div><h3>Устройство</h3><p>Тип, модель и услуга помогут мастеру подготовиться к приёму.</p></div></div>
                    <div class="form-grid">
                        <label class="field form-grid__full<?= isset($errors['device_type']) ? ' field--invalid' : '' ?>" for="admin-request-device-type">
                            <span class="field__label">Тип устройства <span class="field__required" aria-hidden="true">*</span></span>
                            <input id="admin-request-device-type" type="text" name="device_type" value="<?= e($deviceType) ?>" maxlength="100" placeholder="Например, ноутбук или смартфон" required <?= isset($errors['device_type']) ? 'aria-invalid="true" aria-describedby="admin-request-device-type-error"' : '' ?>>
                            <?php if (isset($errors['device_type'])): ?><small class="field__error" id="admin-request-device-type-error"><?= e((string) $errors['device_type']) ?></small><?php endif; ?>
                        </label>

                        <label class="field<?= isset($errors['device_brand']) ? ' field--invalid' : '' ?>" for="admin-request-device-brand">
                            <span class="field__label">Производитель <span class="field__optional">необязательно</span></span>
                            <input id="admin-request-device-brand" type="text" name="device_brand" value="<?= e($deviceBrand) ?>" maxlength="100" placeholder="Например, Lenovo" <?= isset($errors['device_brand']) ? 'aria-invalid="true" aria-describedby="admin-request-device-brand-error"' : '' ?>>
                            <?php if (isset($errors['device_brand'])): ?><small class="field__error" id="admin-request-device-brand-error"><?= e((string) $errors['device_brand']) ?></small><?php endif; ?>
                        </label>

                        <label class="field<?= isset($errors['device_model']) ? ' field--invalid' : '' ?>" for="admin-request-device-model">
                            <span class="field__label">Серия или модель <span class="field__optional">необязательно</span></span>
                            <input id="admin-request-device-model" type="text" name="device_model" value="<?= e($deviceModel) ?>" maxlength="160" placeholder="Например, IdeaPad 5" <?= isset($errors['device_model']) ? 'aria-invalid="true" aria-describedby="admin-request-device-model-error"' : '' ?>>
                            <?php if (isset($errors['device_model'])): ?><small class="field__error" id="admin-request-device-model-error"><?= e((string) $errors['device_model']) ?></small><?php endif; ?>
                        </label>

                        <label class="field form-grid__full<?= isset($errors['service_id']) ? ' field--invalid' : '' ?>" for="admin-request-service">
                            <span class="field__label">Категория и услуга <span class="field__optional">необязательно</span></span>
                            <select id="admin-request-service" name="service_id" <?= isset($errors['service_id']) ? 'aria-invalid="true" aria-describedby="admin-request-service-error"' : '' ?>>
                                <option value="">Не выбрана / нужна диагностика</option>
                                <?php foreach ($serviceGroups as $categoryName => $categoryServices): ?>
                                    <optgroup label="<?= e($categoryName) ?>">
                                        <?php foreach ($categoryServices as $service): ?>
                                            <?php $optionId = (string) ($service['id'] ?? ''); ?>
                                            <option value="<?= e($optionId) ?>" <?= $serviceId === $optionId ? 'selected' : '' ?>><?= e((string) ($service['name'] ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['service_id'])): ?><small class="field__error" id="admin-request-service-error"><?= e((string) $errors['service_id']) ?></small><?php endif; ?>
                            <small class="field__help">Услуги сгруппированы по категориям. Категория сохраняется в заявке через выбранную услугу.</small>
                        </label>
                    </div>
                </div>

                <div class="request-form__section">
                    <div class="request-form__section-head"><span>03</span><div><h3>Описание проблемы</h3><p>Зафиксируйте со слов клиента, что происходит с устройством.</p></div></div>
                    <label class="field<?= isset($errors['problem_description']) ? ' field--invalid' : '' ?>" for="admin-request-problem">
                        <span class="field__label">Что случилось? <span class="field__required" aria-hidden="true">*</span></span>
                        <textarea id="admin-request-problem" name="problem_description" rows="6" maxlength="5000" placeholder="Например: ноутбук выключается под нагрузкой, клиент заметил это неделю назад." required <?= isset($errors['problem_description']) ? 'aria-invalid="true" aria-describedby="admin-request-problem-error"' : '' ?>><?= e($problem) ?></textarea>
                        <?php if (isset($errors['problem_description'])): ?><small class="field__error" id="admin-request-problem-error"><?= e((string) $errors['problem_description']) ?></small><?php else: ?><small class="field__help">Не менее 10 символов. Не записывайте пароли и другие секреты.</small><?php endif; ?>
                    </label>
                </div>

                <div class="request-form__section">
                    <div class="request-form__section-head"><span>04</span><div><h3>Связь с клиентом</h3><p>Контакт нужен, чтобы уточнить детали приёма устройства.</p></div></div>
                    <label class="field<?= isset($errors['customer_contact']) ? ' field--invalid' : '' ?>" for="admin-request-contact">
                        <span class="field__label">Телефон или email клиента <span class="field__required" aria-hidden="true">*</span></span>
                        <input id="admin-request-contact" type="text" name="customer_contact" value="<?= e($contact) ?>" maxlength="80" placeholder="+7 ___ ___ __ __ или client@example.com" required <?= isset($errors['customer_contact']) ? 'aria-invalid="true" aria-describedby="admin-request-contact-error"' : '' ?>>
                        <?php if (isset($errors['customer_contact'])): ?><small class="field__error" id="admin-request-contact-error"><?= e((string) $errors['customer_contact']) ?></small><?php endif; ?>
                    </label>
                </div>

                <div class="request-form__actions">
                    <a class="button button--secondary" href="/admin/requests">Отмена</a>
                    <button class="button button--primary button--large" type="submit">Создать заявку <span aria-hidden="true">↗</span></button>
                </div>
            </form>
        </section>
    </div>
</section>
<?php require dirname(__DIR__, 3) . '/layouts/footer.php'; ?>
