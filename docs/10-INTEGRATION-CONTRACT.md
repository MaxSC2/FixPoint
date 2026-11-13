# Контракт интеграции backend и frontend

**Версия:** 1.0. Единый источник договорённостей по PHP-view данным и HTTP-сценариям.

## Базовые решения
- Серверный PHP-монолит, PHP views. JavaScript только улучшает UX.
- Основные формы работают POST + Post/Redirect/Get.
- Backend отвечает за auth, RBAC, validation, CSRF, бизнес-логику и данные. Views только отображают.
- В шаблонах запрещены SQL/PDO, изменения статусов и проверка полномочий. Все права повторно проверяются сервером.
- Деньги — числа в KZT; даты — DateTimeInterface или ISO 8601.

## Общие данные layout
Контроллер передаёт:
```php
[
  'pageTitle' => 'Название страницы',
  'activeNav' => 'home', // home|services|requests|technician|admin
  'authUser' => null, // ['id'=>1,'full_name'=>'Имя','email'=>'user@example.test','role'=>'customer|technician|admin']
  'flash' => null, // ['type'=>'success|error|info|warning','message'=>'...']
  'errors' => [], 'old' => [], 'csrfToken' => '...'
]
```
Пароли никогда не возвращать в old. Динамические значения экранировать через единый `e()`.

## Формы и ответы
- Все state-changing действия — POST с `_token`.
- Ошибки полей — `errors[field]`; общая ошибка — `errors.general`.
- Успешный POST делает redirect и flash.
- GET никогда не удаляет/не меняет состояние.
- Ошибки 403/404/419/500 не должны раскрывать exception, SQL, пути или секреты.

## Внешние маршруты
| Method | URL | Access | Purpose |
|---|---|---|---|
| GET | `/` | all | Home |
| GET | `/services` | all | Services catalog |
| GET | `/services/{slug}` | all | Service detail |
| GET | `/about` | all | About |
| GET | `/contacts` | all | Contacts |
| GET/POST | `/register` | guest | Registration |
| GET/POST | `/login` | guest | Login |
| POST | `/logout` | auth | Logout |
| GET | `/account` | customer | Client dashboard |
| GET | `/requests` | customer | My requests |
| GET/POST | `/requests/create` | customer | Create request |
| GET | `/requests/{id}` | owner/assigned tech/admin | Request detail |
| POST | `/requests/{id}/comments` | owner/assigned tech/admin | Add comment; staff may set `is_internal=1` |
| POST | `/requests/{id}/estimate-decision` | owner | Approve/reject estimate |
| GET | `/technician/requests` | technician | Work queue, scoped to `technician_id` |
| GET | `/technician/requests/{id}` | assigned tech/admin | Work details; foreign technician gets 404 |
| POST | `/technician/requests/{id}/diagnosis` | assigned tech/admin | Diagnosis and estimate |
| POST | `/technician/requests/{id}/status` | assigned tech/admin | Permitted status transition |
| GET | `/admin` | admin | Dashboard |
| GET | `/admin/requests` | admin | All requests |
| GET/POST | `/admin/requests/{id}/assign` | admin | Assign technician |
| GET/POST | `/admin/users` | admin | Users |
| GET/POST | `/admin/users/{id}/edit` | admin | Edit user |
| GET/POST | `/admin/services` | admin | Services |
| GET/POST | `/admin/services/{id}/edit` | admin | Edit service |
| GET/POST | `/admin/categories` | admin | Categories |
| GET/POST | `/admin/categories/{id}/edit` | admin | Edit category |
| GET | `/admin/statistics` | admin | Analytics |

## Request view model
```php
[
 'id'=>42, 'public_number'=>'FP-2026-000042', 'status'=>'diagnostics',
 'priority'=>'normal', // low|normal|high|urgent
 'device_type'=>'Ноутбук', 'device_brand'=>'ASUS', 'device_model'=>'Model',
 'problem_description'=>'Описание проблемы',
 'diagnostic_result'=>null, 'estimated_cost'=>null, 'final_cost'=>null,
 'estimated_ready_at'=>null, 'customer_contact'=>'+7...',
 'created_at'=>'2026-11-12T10:30:00+05:00', 'updated_at'=>'...',
 'completed_at'=>null,
 'customer'=>['id'=>7,'full_name'=>'Имя'],
 'technician'=>null, 'service'=>null
]
```
Request detail дополнительно: `history[]` (from_status,to_status,reason,changed_at,changed_by_name), `comments[]` (id,body,author_name,author_role,created_at,is_internal). Внутренние заметки не выдавать клиенту: customer view model фильтруется на сервере, а staff view model получает их вместе с флагом. Создавать `is_internal=1` может только назначенный мастер или admin; для остальных флаг игнорируется. `permissions` содержит can_comment, can_decide_estimate, can_edit_diagnosis, can_change_status; эти флаги не заменяют backend auth.

## Статусы
| Code | UI label |
|---|---|
| `new` | Новая |
| `accepted` | Принята |
| `diagnostics` | Диагностика |
| `awaiting_customer_approval` | Ожидает согласования |
| `awaiting_parts` | Ожидает запчасти |
| `repairing` | В ремонте |
| `ready_for_pickup` | Готова к выдаче |
| `completed` | Завершена |
| `cancelled` | Отменена |
| `rejected` | Отклонена |

Переходы: new → accepted/rejected/cancelled; accepted → diagnostics/cancelled; diagnostics → awaiting_customer_approval/awaiting_parts/repairing/rejected; awaiting_customer_approval → repairing/cancelled; repairing → awaiting_parts/ready_for_pickup; awaiting_parts → repairing; ready_for_pickup → completed. completed/cancelled/rejected — конечные. Все переходы проверяются единым RequestStatusService и логируются транзакционно.

## Списки
```php
['items'=>[], 'pagination'=>['current_page'=>1,'last_page'=>1,'per_page'=>10,'total'=>0],
 'filters'=>['q'=>'','status'=>'','sort'=>'newest']]
```
Empty state рендерится честно, без fake rows.

Очередь мастера использует тот же контракт с `filters` = q, status, priority, sort (newest|oldest|priority) и `per_page=8`. `admin` может получить все заявки через сервис, но публичный маршрут очереди остаётся только для `technician`; рабочая карточка и мутации доступны назначенному мастеру и admin. Терминальные заявки (`completed`, `cancelled`, `rejected`) не принимают diagnosis/status-изменения.

## HTTP error policy
- Guest → login redirect с локальным безопасным return_to.
- 403 — вошёл, но прав нет.
- 404 — неизвестная запись или скрытая чужая заявка.
- 419 — CSRF invalid.
- Validation — redirect back with errors/old.
- 500 — нейтральная страница, подробности только в лог.

## Ownership
Agent owns controllers/services/repositories/middleware/security/database/tests/infrastructure. ChatGPT owns Views/assets/prototype/UX docs. Shared bootstrap/router/contracts изменяются только после согласования. Изменения маршрута/view key/status отражаются в контракте и сообщаются второй стороне. Интеграция подтверждается end-to-end HTTP test.