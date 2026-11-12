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
| [Распределение ролей](TEAM_WORK_SPLIT.md) | Кто какие файлы меняет |
| [Передача агенту](AGENT_HANDOFF.md) | Инструкция coding agent |
| [Черновик SQL](database/schema-draft.sql) | Начальная схема БД |
| [Прототип](prototype/README.md) | Статус и назначение UI-референса |
| [Источник оригинального задания](source/ORIGINAL-ASSIGNMENT-SOURCE.md) | Происхождение DOCX |

## Scope

Роли: гость, клиент, мастер и администратор.

Основные модули: публичный сайт и каталог услуг; авторизация; кабинет клиента и заявки; рабочее место мастера; CRUD-панель администратора; история статусов и комментарии; поиск/фильтрация/пагинация; аналитика; безопасность; тесты и развёртывание.

## Stack

PHP 8.3+, MySQL 8, PDO, HTML5, CSS3, JavaScript. Предпочтителен серверный PHP-монолит с progressive enhancement и Docker Compose.

## Current state

На данном этапе в репозитории находятся спецификация, схема БД, правила команды, интеграционный контракт и оригинальный DOCX задания. Производственная реализация приложения ещё не создана. Первая задача coding agent — TASK-001 (backend foundation), первая UI-задача — UI-01.

Функция считается завершённой только после проверки полного сценария с реальной БД. Не допускаются фиктивные обработчики успешной отправки, клиентская безопасность вместо серверной или hardcoded production-данные.
