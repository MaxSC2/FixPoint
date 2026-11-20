# CAL-01: интеграция календаря и записи на встречу

Ветка реализации: `agent/module-5-calendar-appointments-2026-11-20`
Целевая ветка: `feature/module-5-knowledge-base-2026-11-20`
Миграция: `017_create_appointment_calendar.sql` (018–023 не переименовывать)

## Состояние интеграции

- `config/routes.d/calendar.php` явно подключается из `config/routes.php`.
- `app/Core/App.php` не менялся: глобальный auto-discovery route-модулей не добавлялся.
- В шапке отображаются ссылки «Мои встречи» для клиента и «Календарь» для мастера.
- В карточке заявки клиента добавлена кнопка записи; она скрывается для отменённых и отклонённых заявок.
- В рабочей карточке мастера добавлена ссылка на календарь.
- `layouts/footer.php`, `pages/public/home.php`, `public/assets/css/app.css` и `README.md` не менялись.
- `public/assets/css/calendar.css` подключается напрямую календарными view и страницами встреч после `layouts/header.php`.
- Все POST защищены общей `CsrfMiddleware`; роли проверяются `AuthMiddleware` + `RoleMiddleware` на каждой календарной route.
- Время хранится в UTC, часовой пояс мастера используется для слотов и отображения. Частные блоки и `technician_note` не попадают в клиентские проекции.

## 1. Маршруты

Маршруты подключаются явно из `config/routes.php` одной строкой внутри
возвращаемого callable:

```php
    // CAL-01: customer appointments and technician calendar.
    (require __DIR__ . '/routes.d/calendar.php')($router, $app);
```

Ядро `App.php` остаётся без изменений. Не добавляйте параллельный auto-discovery
`config/routes.d/*.php`, иначе один и тот же модуль может зарегистрироваться дважды.

| Метод | URL | Контроллер |
|---|---|---|
| GET | `/appointments` | `CustomerAppointmentController::index` |
| GET | `/appointments/{id}` | `CustomerAppointmentController::show` |
| POST | `/appointments/{id}/cancel` | `CustomerAppointmentController::cancel` |
| POST | `/appointments/{id}/reschedule` | `CustomerAppointmentController::reschedule` |
| POST | `/appointments/{id}/proposal` | `CustomerAppointmentController::proposal` |
| GET | `/requests/{id}/appointments/book` | `CustomerAppointmentController::create` |
| POST | `/requests/{id}/appointments/book` | `CustomerAppointmentController::store` |
| GET | `/technician/calendar` | `TechnicianCalendarController::index` |
| GET/POST | `/technician/calendar/settings` | `TechnicianCalendarController::settings` / `updateSettings` |
| GET/POST | `/technician/calendar/blocks` | `TechnicianCalendarController::blocks` / `storeBlock` |
| POST | `/technician/calendar/blocks/{id}/delete` | `TechnicianCalendarController::deleteBlock` |
| GET | `/technician/appointments/{id}` | `TechnicianAppointmentController::show` |
| POST | `/technician/appointments/{id}/confirm` | `TechnicianAppointmentController::confirm` |
| POST | `/technician/appointments/{id}/decline` | `TechnicianAppointmentController::decline` |
| POST | `/technician/appointments/{id}/reschedule` | `TechnicianAppointmentController::reschedule` |
| POST | `/technician/appointments/{id}/complete` | `TechnicianAppointmentController::complete` |
| POST | `/technician/appointments/{id}/no-show` | `TechnicianAppointmentController::noShow` |

## 2. Кнопка записи в карточке заявки клиента (интегрирована)

Файл: `app/Views/pages/customer/requests/show.php`.

В блоке действий после ссылки «Профиль мастера» (и кнопки отзыва, если она
есть) размещена кнопка:

```php
<?php if (!in_array($requestStatus, ['cancelled', 'rejected'], true)): ?>
    <a class="button button--primary"
       href="/requests/<?= e(rawurlencode($requestId)) ?>/appointments/book">
        Записаться на встречу
    </a>
<?php endif; ?>
```

`$requestStatus` и `$requestId` уже определены этим view. Сервер повторно
проверит владельца, назначенного мастера и статус заявки, поэтому кнопка —
только удобство, не защита.

## 3. Ссылки мастера (интегрированы)

Файл: `app/Views/pages/technician/requests/show.php`, навигация
`workspace-nav`:

```php
<a class="workspace-nav__link" href="/technician/calendar">Календарь</a>
```

Файл: `app/Views/layouts/header.php`, внутри
`<div class="site-header__actions">` после ссылки «Сообщения»:

```php
<?php if (($authUser['role'] ?? '') === 'technician'): ?>
    <a class="header-messages" href="/technician/calendar"><span>Календарь</span></a>
<?php endif; ?>
```

## 4. Ссылка клиента на свои встречи (интегрирована)

Файл: `app/Views/layouts/header.php`, там же:

```php
<?php if (($authUser['role'] ?? '') === 'customer'): ?>
    <a class="header-messages" href="/appointments"><span>Мои встречи</span></a>
<?php endif; ?>
```

Альтернатива без правки header — ссылка в личном кабинете клиента:
`<a class="button button--secondary" href="/appointments">Мои встречи</a>`.

## 5. CSS

`app.css` менять не требуется. Календарные views уже подключают
`/assets/css/calendar.css`; файл использует переменные и радиусы из
`app.css`. Если owner захочет один бандл, достаточно добавить в
`app.css`:

```css
@import url("calendar.css");
```

и убрать `<link>` из календарных views (не обязательно).

## 6. Миграции и проверка

```bash
php database/migrate.php   # применит 017; 021–023 из integration-ветки идут после
php tests/lint.php
php tests/run.php          # DB_DATABASE должен заканчиваться на _test
```

Миграция 017 создаёт только `technician_calendar_settings`,
`technician_weekly_availability`, `technician_calendar_blocks`,
`technician_appointments` и `technician_appointment_events`. Она не ссылается
на таблицы из 018–020, поэтому безопасна при любом порядке уже применённых
миграций 018–023. Повторный запуск `migrate.php` должен вывести
`Nothing to migrate.`


## Итог интеграции

CAL-01 включён в ветку `feature/module-5-admin-ux-audit-2026-11-20` как часть PR #34. Исходная ветка агента сохранена без изменений. Проверка совместимости выполняется на актуальном merge-commit PR #34, а не только на исходной ветке календаря.
