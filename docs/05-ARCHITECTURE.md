# Архитектура FixPoint

## Подход
Серверный PHP-монолит. Front Controller принимает HTTP-запрос, Router выбирает маршрут, Middleware выполняет аутентификацию/проверку роли/CSRF, Controller координирует запрос, Service исполняет бизнес-правила, Repository читает/пишет данные, View отображает результат.

## Слои
- `public/index.php`: единственная публичная точка входа.
- `app/Core/`: Router, Request/Response, ViewRenderer, Database, Config.
- `app/Controllers/`: тонкие HTTP-контроллеры.
- `app/Services/`: правила заявок, авторизация, назначения, каталог.
- `app/Repositories/`: PDO и SQL.
- `app/Middleware/`: Auth, Role, CSRF, SecurityHeaders.
- `app/Views/`: PHP-шаблоны, принадлежат frontend-разработке.
- `database/migrations/`, `database/seeds/`: схема и локальные данные.
- `public/assets/`: CSS/JS/images, frontend-owned.
- `tests/`: unit/integration/smoke.

## Принципы
- Controller не содержит SQL и не реализует переходы статусов.
- Service не формирует HTML.
- Repository не принимает решения о правах доступа.
- View не содержит SQL, PDO или бизнес-мутаций.
- Любое изменение данных идёт через POST и CSRF.
- Валидация выполняется на сервере независимо от HTML attributes.
- Все пользовательские значения экранируются при выводе.
- Конфигурация только из environment; секреты отсутствуют в git.

## Поток запроса
HTTP → Router → Middleware → Controller → Service → Repository/PDO → MySQL → ViewRenderer → HTML response.

## Интеграция
Routes, view model, статусы и error policy зафиксированы в `docs/10-INTEGRATION-CONTRACT.md`. Любое изменение контракта согласуется между frontend и backend и отражается в PR.