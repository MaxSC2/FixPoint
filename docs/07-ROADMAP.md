# Дорожная карта

## Wave 0 — проектная основа
Документы, договорённости, роли, модель данных, контракт, ветки и процесс PR.

## Wave 1 — foundation
Backend: PHP skeleton, router, PDO, config, migrations, seed, Docker, smoke tests.
Frontend: design tokens, shared layout, components, responsive foundation.

## Wave 2 — публичные страницы и авторизация
Public: home, services, about, contacts.
Backend: registration/login/logout, sessions, password hashing, RBAC.
Client UI: login/register, client dashboard.

## Wave 3 — заявки
Backend: create/list/detail, ownership policies, history, comments, estimate decision, RequestStatusService.
Frontend: new request, request list/detail, progress timeline, status states.

## Wave 4 — кабинет мастера
Backend: assignment, diagnosis, estimate, status transition restrictions.
Frontend: queue, request details, diagnosis and repair workspace.

## Wave 5 — администрирование
Backend: CRUD users/services/categories, assignment management, analytics queries.
Frontend: dashboard, management tables, filters, forms, confirmations.

## Wave 6 — интеграция и сдача
End-to-end сценарии, accessibility, responsiveness, security negative tests, CI, setup from clean clone, documentation and screenshots.

## Gate rules
TASK-001 foundation must be reviewed before auth/business modules expand. Each wave ends with working flow, tests and review, not simply completed file count.