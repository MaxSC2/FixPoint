# Работа с репозиторием FixPoint

## Идентификаторы

- GitHub: https://github.com/MaxSC2/FixPoint
- Visibility: public
- Default branch: `main`
- Integration branch: `develop`
- Владелец репозитория принимает финальные решения по scope и merge.

## Ветки и PR

| Ветка | Назначение | Исполнитель |
|---|---|---|
| `main` | Стабильная/релизная версия | Только через PR из develop |
| `develop` | Интеграционная ветка | Только через PR |
| `feature/001-backend-foundation` | Backend foundation | Coding agent |
| `feature/ui-01-design-system` | UI foundation | ChatGPT/frontend |

Feature PR направляются в `develop`. Релизный PR направляется из `develop` в `main`. Прямые коммиты в общие ветки запрещены.

## Владение путями

- Backend agent: `app/Controllers/**`, `app/Services/**`, `app/Repositories/**`, `app/Middleware/**`, `app/Security/**`, `database/**`, `tests/Backend/**`, `tests/Integration/**`, Docker, `.github/workflows/**`, bootstrap/router/config.
- ChatGPT: `app/Views/**`, `public/assets/**`, `prototype/**`, UX/интеграционная документация и визуальный QA.
- Общие файлы изменяются только после согласования в issue/PR.

## Первая задача

Issue #1 — TASK-001: Backend foundation. Агент читает `AGENT_HANDOFF.md` и `docs/12-AGENT-TASK-001-BACKEND-FOUNDATION.md`, создаёт feature-ветку от `develop`, реализует foundation и открывает PR в `develop`. Не переходить к авторизации/CRUD до ревью основы.

## Условия приёмки

Требуются запуск с чистого клона, фактически пройденные тесты, отсутствие секретов и соблюдение ownership. Самоотчёт без проверяемых доказательств не считается приёмкой.