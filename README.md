# FixPoint — система управления заявками сервисного центра

FixPoint — учебное веб-приложение для сервисного центра по ремонту компьютеров, ноутбуков, смартфонов и другой электроники. Цель проекта — полноценная система с PHP-backend, MySQL, ролями пользователей и сквозными сценариями, а не статический макет.

## Repository

- GitHub: https://github.com/MaxSC2/FixPoint
- Visibility: public
- `main`: стабильная версия
- `develop`: интеграционная ветка
- Оригинальное задание: [assignment/Модуль 4.docx](assignment/%D0%9C%D0%BE%D0%B4%D1%83%D0%BB%D1%8C%204.docx)

## Документы проекта

| Документ | Назначение |
|---|---|
| [Паспорт проекта](docs/00-PROJECT-PASSPORT.md) | Цели, пользователи и рамки |
| [Техническое задание](docs/01-TECHNICAL-SPECIFICATION.md) | Функции и требования |
| [Карта Module 4](docs/02-MODULE-4-MAPPING.md) | Связь с требованиями задания |
| [Роли и сценарии](docs/03-ROLES-AND-USER-FLOWS.md) | Путь каждого типа пользователя |
| [Проектирование БД](docs/04-DATABASE-DESIGN.md) | Таблицы, связи, целостность |
| [Архитектура](docs/05-ARCHITECTURE.md) | Слои и поток запроса |
| [План команды](docs/06-TEAM-PLAN.md) | Границы ответственности |
| [Дорожная карта](docs/07-ROADMAP.md) | Волны реализации |
| [Критерии приёмки](docs/08-ACCEPTANCE-CRITERIA.md) | Проверяемое определение готовности |
| [Правила разработки](docs/09-DEVELOPMENT-RULES.md) | Git, ownership, безопасность |
| [Integration contract](docs/10-INTEGRATION-CONTRACT.md) | Контракт frontend/backend |
| [UI specification](docs/11-UI-SPECIFICATION.md) | Визуальная система и страницы |
| [TASK-001 для агента](docs/12-AGENT-TASK-001-BACKEND-FOUNDATION.md) | Backend foundation |
| [Карта ChatGPT](docs/13-CHATGPT-IMPLEMENTATION-MAP.md) | UI/UX, frontend и QA |
| [Repository operations](docs/14-REPOSITORY-OPERATIONS.md) | Ветки и PR |
| [View template contract](docs/16-VIEW-TEMPLATE-CONTRACT.md) | View-модели и разметка UI |
| [UI-01 QA checklist](docs/17-UI-01-QA-CHECKLIST.md) | Проверки визуального слоя |
| [Распределение ролей](TEAM_WORK_SPLIT.md) | Кто какие файлы меняет |
| [Передача агенту](AGENT_HANDOFF.md) | Инструкция coding agent |
| [Черновик SQL](database/schema-draft.sql) | Начальная схема БД |
| [Прототип](prototype/README.md) | Статус и назначение UI-референса |
| [Источник оригинального задания](source/ORIGINAL-ASSIGNMENT-SOURCE.md) | Происхождение DOCX |

## Stack

- PHP 8.3+ (`pdo_mysql`, `mbstring`), серверный PHP-монолит без фреймворка.
- MySQL 8.0+, InnoDB, `utf8mb4_unicode_ci`, PDO с native prepared statements.
- PHP views, HTML5, CSS3, progressive enhancement JavaScript.
- Docker Compose для PHP + MySQL; альтернативный запуск на хосте.

## Быстрый старт: Docker Compose

Требуются Docker Desktop / Docker Engine с Compose v2.

```bash
cp .env.example .env
```

В `.env` задайте локальные значения:

```dotenv
DB_DATABASE=fixpoint
DB_USERNAME=fixpoint
DB_PASSWORD=change_me_locally
DB_ROOT_PASSWORD=change_me_root_locally
SEED_PASSWORD=FixPoint!Dev1
```

Запуск:

```bash
docker compose up --build
```

- Приложение: http://localhost:8080
- Проверка состояния: http://localhost:8080/health
- MySQL доступен только внутри compose-сети; данные хранятся в volume `db_data`.
- Контейнер приложения ждёт healthcheck БД, выполняет миграции, затем seed
  (`RUN_MIGRATIONS=true`, `SEED_DATABASE=true`).
- Порт приложения переопределяется через `APP_PORT` (по умолчанию `8080`).

Остановка:

```bash
docker compose down          # сохранить данные
docker compose down -v       # удалить БД и начать заново
```

## Запуск без Docker

Требования: PHP 8.3+ с расширениями `pdo_mysql` и `mbstring`, MySQL 8.0+.

1. Создайте базу и пользователя (пример):

   ```sql
   CREATE DATABASE fixpoint CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'fixpoint'@'localhost' IDENTIFIED BY 'change_me_locally';
   GRANT ALL PRIVILEGES ON fixpoint.* TO 'fixpoint'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. Скопируйте `.env.example` в `.env` и укажите параметры подключения.
   Для локального MySQL обычно нужны `DB_HOST=127.0.0.1`, `DB_PORT=3306`.

3. Установите зависимости и примените схему:

   ```bash
   php database/migrate.php
   php database/seed.php
   ```

4. Запустите встроенный сервер:

   ```bash
   php -S 127.0.0.1:8080 -t public public/index.php
   ```

   Откройте http://localhost:8080. Для Apache/nginx document root должен
   указывать на `public/`; `public/.htaccess` содержит rewrite-правила.

Полезные команды:

```bash
php database/migrate.php --status   # состояние миграций
php database/migrate.php --fresh    # пересоздать схему (local/testing)
php database/seed.php --force       # seed в production только осознанно
```

## Локальные seed-аккаунты

Все пароли по умолчанию — `FixPoint!Dev1`; значение задаётся `SEED_PASSWORD`
в `.env`. Это **только локальные демо-данные**, использовать их в production
запрещено. Seed не перезаписывает существующие хеши паролей.

| Роль | Email |
|---|---|
| Администратор | `admin@fixpoint.test` |
| Мастер | `master.ivanov@fixpoint.test` |
| Мастер | `master.smirnova@fixpoint.test` |
| Клиент | `customer.kovalev@fixpoint.test` |
| Клиент | `customer.ivanova@fixpoint.test` |
| Неактивный клиент | `inactive@fixpoint.test` |

Seed создаёт 5 категорий, 10 услуг, 4 заявки, 15 записей истории статусов и
5 комментариев (включая внутренний). Повторный запуск идемпотентен и не
перезаписывает уже изменённые пользователем записи.

## Маршруты backend foundation

| Метод | URL | Назначение |
|---|---|---|
| GET | `/` | Главная, реальные категории и услуги из MySQL |
| GET | `/services` | Каталог: поиск `q`, фильтр `category`, пагинация `page` |
| GET | `/services/{slug}` | Карточка услуги |
| GET | `/about` | Страница «О сервисе» |
| GET | `/contacts` | Контакты; значения из `CONTACT_*`, пустые — явный placeholder |
| GET | `/health` | JSON-статус приложения и БД (200/503) |

### Авторизация и роли

| Метод | URL | Доступ | Назначение |
|---|---|---|---|
| GET | `/login` | гость | Форма входа |
| POST | `/login` | гость | Проверка пароля и вход; CSRF обязателен |
| GET | `/register` | гость | Форма регистрации |
| POST | `/register` | гость | Создание аккаунта с ролью `customer` |
| POST | `/logout` | авторизованный | Выход и ротация сессии |
| GET | `/account` | `customer` | Кабинет: только заявки текущего клиента |
| GET | `/technician/requests` | `technician` | Очередь: только назначенные мастеру заявки |
| GET | `/technician/requests/{id}` | назначенный мастер, `admin` | Рабочая карточка заявки |
| GET | `/admin` | `admin` | Сводка по заявкам, пользователям и каталогу |
| GET | `/admin/requests` | `admin` | Глобальный реестр заявок; `q`, `status`, `priority`, `sort`, `page` |
| GET | `/admin/requests/{id}/assign` | `admin` | Форма назначения мастера |
| POST | `/admin/requests/{id}/assign` | `admin` | Назначение активного мастера; терминальные заявки отклоняются |
| GET | `/admin/users` | `admin` | Список учётных записей и форма создания |
| POST | `/admin/users` | `admin` | Создание пользователя с ролью и временным паролем |
| GET | `/admin/users/{id}/edit` | `admin` | Редактирование профиля, роли и активности |
| POST | `/admin/users/{id}/edit` | `admin` | Сохранение профиля; пароль не меняется |

Гость, открывший защищённый URL, получает redirect на
`/login?return_to=...`; возврат разрешён только на локальный путь. Чужая роль
получает 403, а авторизованный пользователь не может открыть `/login` или
`/register` — его перенаправляет на домашнюю страницу роли. Регистрация
создаёт только `customer`; роли `technician` и `admin` назначаются
администратором.

### Заявки клиента

| Метод | URL | Доступ | Назначение |
|---|---|---|---|
| GET | `/requests` | `customer` | Список только своих заявок; `q`, `status`, `sort`, `page` |
| GET | `/requests/create` | `customer` | Форма создания заявки |
| POST | `/requests/create` | `customer` | Валидация, создание заявки и первой записи истории в одной транзакции |
| GET | `/requests/{id}` | владелец, назначенный мастер, `admin` | Детали, история и публичные комментарии; чужим — 404 |
| POST | `/requests/{id}/comments` | владелец, назначенный мастер, `admin` | Комментарий; staff может создать внутреннюю заметку, клиент — нет |
| POST | `/requests/{id}/estimate-decision` | владелец, статус `awaiting_customer_approval` | Согласование → `repairing`, отказ → `cancelled` |

Переходы статусов централизованы в `RequestStatusService` и совпадают с
матрицей `docs/10-INTEGRATION-CONTRACT.md`. Смена статуса и запись в
`request_status_history` выполняются атомарно; владение и роль повторно
проверяются в сервисе, а не только маршрутным middleware.

### Мастерская

| Метод | URL | Доступ | Назначение |
|---|---|---|---|
| GET | `/technician/requests` | `technician` | Очередь только назначенных мастеру заявок; `q`, `status`, `priority`, `sort`, `page` |
| GET | `/technician/requests/{id}` | назначенный мастер, `admin` | Рабочая карточка: устройство, клиент, диагностика, смета, история, комментарии |
| POST | `/technician/requests/{id}/diagnosis` | назначенный мастер, `admin` | Сохранение диагностики, стоимости и даты готовности |
| POST | `/technician/requests/{id}/status` | назначенный мастер, `admin` | Переход только из матрицы `RequestStatusService` |

Очередь мастера отдаёт по 8 заявок на страницу и сортируется по новизне,
старости или приоритету. Чужая заявка в деталях и мутациях даёт 404, а не
403, чтобы не раскрывать её существование. `admin` может открыть рабочую
карточку и выполнить разрешённые действия, но очередь `/technician/requests`
остаётся только для роли `technician`.

Внутренние заметки (`is_internal=1`) создают только назначенный мастер или
`admin`; для клиента флаг игнорируется. Клиентские view-модели не содержат
внутренних заметок, а staff-страница помечает их отдельным бейджем.
Терминальные заявки (`completed`, `cancelled`, `rejected`) доступны только
для чтения.

### Администрирование

| Метод | URL | Доступ | Назначение |
|---|---|---|---|
| GET | `/admin` | `admin` | Дашборд: агрегаты по заявкам, клиентам и мастерам |
| GET | `/admin/requests` | `admin` | Все заявки без ограничения по владельцу; 8 записей на страницу |
| GET | `/admin/requests/{id}/assign` | `admin` | Форма назначения мастера |
| POST | `/admin/requests/{id}/assign` | `admin` | Сохранение назначения; только активный пользователь с ролью `technician` |
| GET | `/admin/users` | `admin` | Все учётные записи и форма создания |
| POST | `/admin/users` | `admin` | Создание пользователя: валидация, роль, хеширование пароля |
| GET | `/admin/users/{id}/edit` | `admin` | Форма профиля, роли и активности |
| POST | `/admin/users/{id}/edit` | `admin` | Обновление без смены пароля; защита последнего администратора |

Реестр поддерживает поиск по номеру, клиенту, устройству и услуге, фильтры
`status`/`priority` и сортировку `newest`/`oldest`/`updated`. Назначение не
меняет статус заявки: мастер продвигает workflow отдельными действиями.
Неактивный пользователь, пользователь с другой ролью и терминальная заявка
отклоняются сервером; попытка назначения на завершённую заявку возвращает
ошибку, а не меняет запись.

Управление пользователями создаёт только активные учётные записи: форма
создания не содержит переключателя активности. Пароль хешируется и никогда
не возвращается в `old` или HTML. Редактирование меняет имя, email, телефон,
роль и активность, но не пароль. Сервер запрещает снять с себя роль
администратора, отключить собственную учётную запись и оставить систему без
активного администратора. После отключения или смены роли доступ
пересчитывается на следующем запросе: `Auth` читает пользователя из БД и
проверяет `is_active`, поэтому старая сессия не сохраняет прежние права.

## Структура

```text
app/
  bootstrap.php          # конфигурация, autoload, front controller bootstrap
  Controllers/           # тонкие HTTP-контроллеры
  Core/                  # App, Config, Env, Request, Response, Router, Session
  Database/              # Migrator, Seeder
  Middleware/            # Auth, Role, CSRF, SecurityHeaders
  Repositories/          # весь SQL (PDO prepared statements)
  Security/              # Auth, Csrf, PasswordHasher
  Services/              # Auth, Catalog, Request, RequestStatus, DatabaseHealth
  Support/views/         # временные backend-fallback страницы
config/routes.php        # явная таблица маршрутов
database/migrations/     # 6 forward-only SQL-миграций
database/seeds/          # локальные демо-данные
docker/                  # php.ini, Apache vhost, entrypoint, wait-for-db
public/                  # единственный document root, index.php, .htaccess
tests/                   # unit, integration, lint, smoke
```

`app/Views/**` содержит основные шаблоны публичной части, auth и ролевых
кабинетов, `public/assets/**` — стили и progressive enhancement. Публичные
страницы, формы входа/регистрации, заявки клиента и рабочее место мастера
используют единый визуальный слой из `docs/16-VIEW-TEMPLATE-CONTRACT.md`.
Если шаблон существует в `app/Views`, renderer использует его; иначе — явно
помеченный fallback из `app/Support/views`. Backend не должен дублировать
разметку в контроллерах.

## Helpers

Имена задокументированы в `app/Support/helpers.php`:

| Helper | Назначение |
|---|---|
| `e($value)` | HTML-экранирование (`ENT_QUOTES`, UTF-8) |
| `url($path)`, `asset($path)` | Абсолютные URL от `APP_URL` |
| `redirect($to)`, `back($request)` | Только локальные безопасные переходы |
| `csrf_token()`, `csrf_field()` | Токен и hidden-input для форм |
| `flash($type, $message)` | Одноразовое сообщение |
| `flash_errors($errors)`, `flash_old($input)` | Ошибки валидации и old input |
| `auth_user()`, `has_role(...$roles)` | Текущий пользователь и RBAC-проверка |
| `old_value($old, $key, $default)` | Безопасное значение old input |
| `format_money()`, `format_datetime()` | Деньги в KZT и даты в `APP_TIMEZONE` |
| `status_label($status)` | Русские подписи статусов заявок |
| `app()`, `config($key)`, `base_path($path)` | Доступ к приложению и путям |

## Тесты и проверки

```bash
php tests/lint.php          # php -l по всем PHP-файлам проекта
php tests/run.php           # unit + integration
php tests/smoke.php         # реальный HTTP через встроенный сервер
```

Перед интеграционными тестами runner сам делает `migrate --fresh` и seed в
отдельной БД. Имя БД обязано заканчиваться на `_test` — это защита от
случайного удаления рабочих данных.

PowerShell:

```powershell
$env:DB_DATABASE='fixpoint_test'
$env:APP_ENV='testing'
php tests/run.php
```

Bash:

```bash
DB_DATABASE=fixpoint_test APP_ENV=testing php tests/run.php
```

Только unit-тесты (без БД): `php tests/run.php --unit`.
Smoke-тест требует уже применённые миграции и seed в рабочей БД; порт
переопределяется `SMOKE_PORT` (по умолчанию `8091`).

Текущий результат на MySQL 8.4.6: линтер — 126/126 файлов, тесты — 162 passed,
0 failed, 1404 assertions, smoke — 30/30 проверок.

## Безопасность

- PDO: `ERRMODE_EXCEPTION`, `ATTR_EMULATE_PREPARES=false`, `utf8mb4`, UTC.
- Все SQL-запросы — в репозиториях, только prepared statements; LIMIT/OFFSET
  проходят целочисленную валидацию.
- Пароли — `password_hash()` / `password_verify()`; хеши не попадают в views.
- CSRF-токен на сессию, сравнение через `hash_equals()`, ротация при
  login/logout. Проверка включена глобально в `App::dispatch()` для всех
  `POST`/`PUT`/`PATCH`/`DELETE`: запрос без валидного токена получает 419,
  даже если новый маршрут забыл подключить middleware.
- RBAC проверяется на сервере: роль из сессии сверяется с ролями маршрута,
  клиентские выборки заявок ограничены `customer_id`, мастерские —
  `technician_id`.
- Сессии: HttpOnly, SameSite, strict mode, regenerated id, idle timeout.
- Security headers на всех ответах, включая 404/500: CSP, `X-Frame-Options:
  DENY`, `nosniff`, Referrer-Policy, Permissions-Policy.
- 500 не раскрывает SQL, пути и stack trace: в ответе только correlation id,
  подробности — в `storage/logs/app.log`.
- HTML-вывод экранируется через `e()`; XSS-регрессии покрыты HTTP-тестами.

## Ограничения текущего этапа

- Клиентский поток заявок работает: создание, scoped-список, фильтры,
  пагинация, детали, комментарии и решение по смете.
- Мастерская работает: scoped-очередь с фильтрами и пагинацией, диагностика
  и смета, разрешённые переходы статусов с историей, внутренние заметки.
- Админский реестр заявок и назначение мастера работают: глобальные фильтры,
  пагинация, проверка роли/активности исполнителя и защита терминальных
  заявок. Управление пользователями работает: список, создание с ролью,
  редактирование профиля и активности, защита последнего администратора.
  Админ-CRUD услуг, категорий и статистика — следующие этапы TASK-005.
- Публичная часть, auth-страницы, клиентские заявки, рабочее место мастера,
  админ-дашборд, реестр заявок и управление пользователями переведены на общий
  UI-слой (адаптив, reveal-анимации, состояния форм). Ссылки на
  `/admin/services`, `/admin/categories` и `/admin/statistics` появятся вместе
  с их маршрутами.
- `/about` и `/contacts` имеют реальные контроллеры и шаблоны в `app/Views`;
  backend-fallback остаётся страховкой для шаблонов, которых ещё нет.
- Административные разделы услуг, категорий и статистики пока не выполняют
  state-changing действий; они появятся в следующих этапах TASK-005.
- `request_status_history.created_at` остаётся в схеме; для view-модели он
  отдаётся alias-ом `changed_at` (решение зафиксировано).
- `request_status_history.changed_by` остаётся `NOT NULL`: все переходы MVP
  инициируются клиентом, мастером или администратором.

## License

Учебный проект; лицензия не задана.
