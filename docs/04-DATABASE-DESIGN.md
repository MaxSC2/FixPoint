# Проектирование базы данных

База: MySQL 8, InnoDB, utf8mb4. Все идентификаторы — BIGINT UNSIGNED. Время хранить в UTC, отображать в локальной зоне приложения.

## Таблицы

### users
`id`, `full_name`, `email` UNIQUE, `phone`, `password_hash`, `role` ENUM(customer,technician,admin), `is_active`, `created_at`, `updated_at`.

### service_categories
`id`, `name`, `slug` UNIQUE, `description`, `sort_order`, `is_active`.

### services
`id`, `category_id` FK, `name`, `slug` UNIQUE, `short_description`, `description`, `price_from` DECIMAL(10,2), `estimated_minutes`, `is_active`.

### repair_requests
`id`, `public_number` UNIQUE, `customer_id` FK users, `technician_id` nullable FK users, `service_id` nullable FK services, `status`, `priority`, `device_type`, `device_brand`, `device_model`, `problem_description`, `diagnostic_result`, `estimated_cost`, `final_cost`, `estimated_ready_at`, `customer_contact`, `created_at`, `updated_at`, `completed_at`.

### request_status_history
`id`, `request_id` FK, `from_status`, `to_status`, `reason`, `changed_by` FK users, `created_at`. Индекс по request_id + created_at.

### request_comments
`id`, `request_id` FK, `author_id` FK users, `body`, `is_internal`, `created_at`. Внутренние заметки нельзя выдавать клиенту.

### Дополнительные таблицы
При необходимости: `password_reset_tokens`, `sessions` (если не используется файловое хранилище сессионных данных), `audit_logs`. Не добавлять таблицы ради вида.

## Правила целостности
- email уникален; роль ограничена allowlist.
- customer_id обязателен; technician_id может быть NULL до назначения.
- Цена DECIMAL, не FLOAT.
- Удаление пользователей/услуг не должно ломать историю: использовать ограничение/soft delete.
- Изменение статуса и запись истории выполнять в одной транзакции.
- Seed идемпотентен и содержит только фиктивные локальные данные.
- Внешние ключи и индексы для всех частых фильтров: status, customer_id, technician_id, created_at.